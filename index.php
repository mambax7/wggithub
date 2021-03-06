<?php
/*
 You may not change or alter any portion of this comment or credits
 of supporting developers from this source code or any supporting source code
 which is considered copyrighted (c) material of the original comment or credit authors.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
*/

/**
 * wgGitHub module for xoops
 *
 * @copyright      2020 XOOPS Project (https://xooops.org)
 * @license        GPL 2.0 or later
 * @package        wggithub
 * @since          1.0
 * @min_xoops      2.5.10
 * @author         Goffy - XOOPS Development Team - Email:<goffy@wedega.com> - Website:<https://wedega.com>
 */

use Xmf\Request;
use XoopsModules\Wggithub;
use XoopsModules\Wggithub\{
    Constants,
    Helper,
    Github\Github,
    Github\Repositories,
    Github\Releases
};

require __DIR__ . '/header.php';
$GLOBALS['xoopsOption']['template_main'] = 'wggithub_index.tpl';
include_once \XOOPS_ROOT_PATH . '/header.php';

// Permissions
$permGlobalView = $permissionsHandler->getPermGlobalView();
if (!$permGlobalView) {
    $GLOBALS['xoopsTpl']->assign('error', _NOPERM);
    require __DIR__ . '/footer.php';
}
$permGlobalRead = $permissionsHandler->getPermGlobalRead();
$permReadmeUpdate = $permissionsHandler->getPermReadmeUpdate();

$op            = Request::getCmd('op', 'list');
$filterRelease = Request::getString('release', 'all');
$filterSortby  = Request::getString('sortby', 'update');

$GLOBALS['xoopsTpl']->assign('release', $filterRelease);
$GLOBALS['xoopsTpl']->assign('sortby', $filterSortby);

// Define Stylesheet
$GLOBALS['xoTheme']->addStylesheet($style, null);
$GLOBALS['xoTheme']->addStylesheet(WGGITHUB_URL . '/assets/css/tabs.css', null);
$keywords = [];
// 
$GLOBALS['xoopsTpl']->assign('xoops_icons32_url', XOOPS_ICONS32_URL);
$GLOBALS['xoopsTpl']->assign('wggithub_url', WGGITHUB_URL);
$GLOBALS['xoopsTpl']->assign('wggithub_image_url', WGGITHUB_IMAGE_URL);
//
$GLOBALS['xoopsTpl']->assign('permReadmeUpdate', $permissionsHandler->getPermReadmeUpdate());

switch ($op) {
    case 'show':
    case 'list':
    case 'apiexceed':
    default:
        //check number of API calls
        $crRequests = new \CriteriaCompo();
        $crRequests->add(new \Criteria('req_datecreated', (time() - 3600), '>'));
        $requestsCount = $requestsHandler->getCount($crRequests);
        if ($permGlobalRead && $requestsCount < 60 && 'list' == $op) {
            executeUpdate();
        }

        unset($crRequests);
        $crRequests = new \CriteriaCompo();
        $crRequests->add(new \Criteria('req_result', 'OK'));
        $crRequests->setStart(0);
        $crRequests->setLimit(1);
        $crRequests->setSort('req_id');
        $crRequests->setOrder('DESC');
        $requestsAll = $requestsHandler->getAll($crRequests);
        foreach (\array_keys($requestsAll) as $i) {
            $lastUpdate = $requestsAll[$i]->getVar('req_datecreated');
            $GLOBALS['xoopsTpl']->assign('lastUpdate', \formatTimestamp($lastUpdate, 'm'));
        }
        unset($crRequests);
        $crRequests = new \CriteriaCompo();
        $crRequests->setStart(0);
        $crRequests->setLimit(1);
        $crRequests->setSort('req_id');
        $crRequests->setOrder('DESC');
        $requestsAll = $requestsHandler->getAll($crRequests);
        foreach (\array_keys($requestsAll) as $i) {
            if ($lastUpdate < $requestsAll[$i]->getVar('req_datecreated')) {
                if (\strpos($requestsAll[$i]->getVar('req_result'), 'API rate limit exceeded') > 0) {
                    $GLOBALS['xoopsTpl']->assign('apiexceed', true);
                } else {
                    $GLOBALS['xoopsTpl']->assign('apierror', true);
                }
            }
        }
        unset($crRequests);

        $github = Github::getInstance();
        $start = Request::getInt('start', 0);
        $limit = Request::getInt('limit', $helper->getConfig('userpager'));
        $menu  = Request::getInt('menu', 0);

        $crDirectories = new \CriteriaCompo();
        $crDirectories->add(new \Criteria('dir_online', 1));
        $directoriesCount = $directoriesHandler->getCount($crDirectories);
        $GLOBALS['xoopsTpl']->assign('directoriesCount', $directoriesCount);
        if ($directoriesCount > 0) {
            $directoriesAll = $directoriesHandler->getAll($crDirectories);
            // Get All Directories
            $directories = [];
            foreach (\array_keys($directoriesAll) as $i) {
                $directories[$i] = $directoriesAll[$i]->getValuesDirectories();
                $dirName = $directoriesAll[$i]->getVar('dir_name');
                $dirFilterRelease = (bool)$directoriesAll[$i]->getVar('dir_filterrelease');
                $repos = [];
                $crRepositories = new \CriteriaCompo();
                $crRepositories->add(new \Criteria('repo_user', $dirName));
                $repositoriesCountTotal = $repositoriesHandler->getCount($crRepositories);
                if ('any' === $filterRelease && $dirFilterRelease) {
                    $crRepositories->add(new \Criteria('repo_prerelease', 1) );
                    $crRepositories->add(new \Criteria('repo_release', 1), 'OR');
                }
                if ('final' === $filterRelease && $dirFilterRelease) {
                    $crRepositories->add(new \Criteria('repo_release', 1));
                }
                $repositoriesCount = $repositoriesHandler->getCount($crRepositories);
                $crRepositories->setStart($start);
                $crRepositories->setLimit($limit);
                switch ($filterSortby) {
                    case 'name':
                    default:
                        $crRepositories->setSort('repo_name');
                        $crRepositories->setOrder('ASC');
                        break;
                    case 'update':
                        $crRepositories->setSort('repo_updatedat');
                        $crRepositories->setOrder('DESC');
                        break;
                }
                if ($repositoriesCount > 0) {
                    $repositoriesAll = $repositoriesHandler->getAll($crRepositories);
                    foreach (\array_keys($repositoriesAll) as $j) {
                        $repoId = $repositoriesAll[$j]->getVar('repo_id');
                        $repos[$j] = $repositoriesAll[$j]->getValuesRepositories();
                        $repos[$j]['readme'] = ['content_clean' => _MA_WGGITHUB_README_NOFILE];
                        if ($repositoriesAll[$j]->getVar('repo_readme') > 0) {
                            $crReadmes = new \CriteriaCompo();
                            $crReadmes->add(new \Criteria('rm_repoid', $repoId));
                            $readmesAll = $readmesHandler->getAll($crReadmes);
                            foreach ($readmesAll as $readme) {
                                $repos[$j]['readme'] = $readme->getValuesReadmes();
                            }
                            unset($crReadmes, $readmesAll);
                        }
                        if ($repositoriesAll[$j]->getVar('repo_prerelease') > 0 || $repositoriesAll[$j]->getVar('repo_release') > 0) {
                            //$repos[$j]['releases'] = [];
                            $crReleases = new \CriteriaCompo();
                            $crReleases->add(new \Criteria('rel_repoid', $repoId));
                            $releasesAll = $releasesHandler->getAll($crReleases);
                            foreach ($releasesAll as $release) {
                                $repos[$j]['releases'][] = $release->getValuesReleases();
                            }
                            unset($crReleases, $releasesAll);
                        }
                    }
                    unset($crRepositories, $repositoriesAll);
                }
                if ($repositoriesCount === $repositoriesCountTotal) {
                    $directories[$i]['countRepos'] = str_replace(['%s', '%t'], [$dirName, $repositoriesCountTotal], _MA_WGGITHUB_REPOSITORIES_COUNT2);
                } else {
                    $directories[$i]['countRepos'] = str_replace(['%s', '%r', '%t'], [$dirName, $repositoriesCount, $repositoriesCountTotal], _MA_WGGITHUB_REPOSITORIES_COUNT1);
                }
                $directories[$i]['repos'] = $repos;
                $directories[$i]['previousRepos'] = $start > 0;
                $directories[$i]['previousOp'] = '&amp;start=' . ($start - $limit) . '&amp;limit=' . $limit . '&amp;release=' . $filterRelease . '&amp;sortby=' . $filterSortby;
                $directories[$i]['nextRepos'] = ($repositoriesCount - $start) > $limit;
                $directories[$i]['nextOp'] = '&amp;start=' . ($start + $limit) . '&amp;limit=' . $limit . '&amp;release=' . $filterRelease . '&amp;sortby=' . $filterSortby;
                $GLOBALS['xoopsTpl']->assign('start', $start);
                $GLOBALS['xoopsTpl']->assign('limit', $limit);
                $GLOBALS['xoopsTpl']->assign('menu', $menu);

                $GLOBALS['xoopsTpl']->assign('directories', $directories);
            }

            unset($crDirectories, $directories);
            // Display Navigation
            if ($directoriesCount > $limit) {
                include_once \XOOPS_ROOT_PATH . '/class/pagenav.php';
                $pagenav = new \XoopsPageNav($directoriesCount, $limit, $start, 'start', 'op=list&limit=' . $limit);
                $GLOBALS['xoopsTpl']->assign('pagenav', $pagenav->renderNav(4));
            }
            $GLOBALS['xoopsTpl']->assign('lang_thereare', \sprintf(\_MA_WGGITHUB_INDEX_THEREARE, $directoriesCount));
            $GLOBALS['xoopsTpl']->assign('divideby', $helper->getConfig('divideby'));
            $GLOBALS['xoopsTpl']->assign('numb_col', $helper->getConfig('numb_col'));
        }

        break;
    case 'update':
        // Permissions
        if (!$permGlobalRead) {
            $GLOBALS['xoopsTpl']->assign('error', _NOPERM);
            require __DIR__ . '/footer.php';
        }
        executeUpdate();
        break;
    case 'update_readme':
        // Permissions
        if (!$permReadmeUpdate) {
            $GLOBALS['xoopsTpl']->assign('error', _NOPERM);
            require __DIR__ . '/footer.php';
        }
        $repoId = Request::getInt('repo_id', 0);
        $repoUser  = Request::getString('repo_user', 'none');
        $repoName  = Request::getString('repo_name', 'none');
        $res = $helper->getHandler('Readmes')->updateReadmes($repoId, $repoUser, $repoName);
        break;
}

$GLOBALS['xoopsTpl']->assign('table_type', $helper->getConfig('table_type'));
// Breadcrumbs
$xoBreadcrumbs[] = ['title' => \_MA_WGGITHUB_INDEX];
// Keywords
wggithubMetaKeywords($helper->getConfig('keywords') . ', ' . \implode(',', $keywords));
unset($keywords);
// Description
wggithubMetaDescription(\_MA_WGGITHUB_INDEX_DESC);
$GLOBALS['xoopsTpl']->assign('xoops_mpageurl', WGGITHUB_URL.'/index.php');
$GLOBALS['xoopsTpl']->assign('xoops_icons32_url', XOOPS_ICONS32_URL);
$GLOBALS['xoopsTpl']->assign('wggithub_upload_url', WGGITHUB_UPLOAD_URL);
require __DIR__ . '/footer.php';



/**
 * Execute update of repositories and all related tables
 *
 * @return bool
 */
function executeUpdate(){

    $github = Github::getInstance();
    $helper = Helper::getInstance();
    $directoriesHandler = $helper->getHandler('Directories');
    $releasesHandler = $helper->getHandler('Releases');
    $readmesHandler = $helper->getHandler('Readmes');
    $crDirectories = new \CriteriaCompo();
    $crDirectories->add(new \Criteria('dir_autoupdate', 1));
    $crDirectories->add(new \Criteria('dir_online', 1));
    $directoriesAll = $directoriesHandler->getAll($crDirectories);
    // Get All Directories
    $directories = [];
    foreach (\array_keys($directoriesAll) as $i) {
        $directories[$i] = $directoriesAll[$i]->getValuesDirectories();
        if (1 === (int) $directoriesAll[$i]->getVar('dir_autoupdate')) {
            $dirName = $directoriesAll[$i]->getVar('dir_name');
            $repos = [];
            for ($j = 1; $j <= 9; $j++) {
                $repos[$j] = [];
                if (Constants::DIRECTORY_TYPE_ORG == $directoriesAll[$i]->getVar('dir_type')) {
                    $repos = $github->readOrgRepositories($dirName, 100, $j);
                } else {
                    $repos = $github->readUserRepositories($dirName, 100, $j);
                }
                if (false === $repos) {
                    return false;
                    break 1;
                }
                if (count($repos) > 0) {
                    $github->updateTableRepositories($dirName, $repos, true);
                } else {
                    break 1;
                }
                if (count($repos) < 100) {
                    break 1;
                }
            }
        }
    }
    unset($directories);

    $releasesHandler->updateRepoReleases();
    $readmesHandler->updateRepoReadme();


    return true;
}
