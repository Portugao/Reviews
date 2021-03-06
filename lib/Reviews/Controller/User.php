<?php
/**
 * Zikula Application Framework
 *
 * @copyright (c) 2002, Zikula Development Team
 * @link http://www.zikula.org
 * @version $Id: User.php 445 2010-07-06 16:09:10Z drak $
 * @license GNU/GPL - http://www.gnu.org/copyleft/gpl.html
 * @package Zikula_Value_Addons
 * @subpackage Reviews
 */

class Reviews_Controller_User extends Zikula_AbstractController 
{
    /**
     * the main user function
     *
     * @param integer startnum starting number of the page
     * @return string HTML output
     */
    public function main()
    {
        // Security check
        if (!SecurityUtil::checkPermission('Reviews::', '::', ACCESS_OVERVIEW)) {
            return LogUtil::registerPermissionError();
        }

        // Get parameters from whatever input we need
        $startnum = (int)FormUtil::getPassedValue('startnum', 1, 'REQUEST');

        // get all module vars for later use
        $modvars = ModUtil::getVar('Reviews');

        // Get all matching reviews
        $recentitems = ModUtil::apiFunc('Reviews', 'user', 'getall',
                array('startnum' => $startnum,
                'orderby' => 'cr_date DESC',
                'numitems' => 10));
        // get the most popular reviews
        $popularitems = ModUtil::apiFunc('Reviews', 'user', 'getall',
                array('startnum' => $startnum,
                'orderby' => 'hits DESC',
                'numitems' => 10));

        // load the categories system
        if ($modvars['enablecategorization']) {
            $catregistry = CategoryRegistryUtil::getRegisteredModuleCategories('Reviews', 'reviews');
            $categories = array();
            $ak = array_keys($catregistry);
            foreach ($ak as $k) {
                $categories[$k] = CategoryUtil::getCategoryByID($catregistry[$k]);
                $categories[$k]['path'] .= '/';
                $categories[$k]['subcategories'] = CategoryUtil::getCategoriesByParentID($catregistry[$k]);
            }
            $this->view->assign('categories', $categories);
        }

        // assign item arrays to template
        $this->view->assign('lang', ZLanguage::getLanguageCode());
        $this->view->assign($modvars);
        $this->view->assign('shorturls',      System::getVar('shorturls'));
        $this->view->assign('shorturlstype',  System::getVar('shorturlstype'));
        $this->view->assign('recentreviews',  $recentitems);
        $this->view->assign('popularreviews', $popularitems);

        // Return the output that has been generated by this function
        return $this->view->fetch('reviews_user_main.htm');
    }

    /**
     * add new item
     *
     * @return string HTML output
     */
    public function newreview()
    {
        // Security chec
        if (!SecurityUtil::checkPermission('Reviews::', '::', ACCESS_ADD)) {
            return LogUtil::registerPermissionError();
        }

        // get module vars for later use
        $modvars = ModUtil::getVar('Reviews');

        if ($modvars['enablecategorization']) {
            $catregistry = CategoryRegistryUtil::getRegisteredModuleCategories('Reviews', 'reviews');

            $this->view->assign('categories', $catregistry);
        }

        // Assign the default language and module config
        $this->view->assign('lang', ZLanguage::getLanguageCode());
        $this->view->assign($modvars);

        // get the type parameter so we can decide what template to use
        $type = $this->request->query->filter('type', 'user', FILTER_SANITIZE_STRING);

        // Return the output that has been generated by this function
        if (strtolower($type) == 'admin') {
            return $this->view->fetch('reviews_admin_new.htm');
        } else {
            return $this->view->fetch('reviews_user_new.htm');
        }
    }

    /**
     * create review
     */
    public function create($args)
    {
        // Get parameters from whatever input we need
        $review = FormUtil::getPassedValue('review', isset($args['review']) ? $args['review'] : null, 'POST');

        // Confirm authorisation code
        if (!SecurityUtil::confirmAuthKey()) {
            return LogUtil::registerAuthidError(ModUtil::url('Reviews', 'user', 'view'));
        }

        // Notable by its absence there is no security check here
        // create the review
        $id = ModUtil::apiFunc('Reviews', 'user', 'create', $review);
        if ($id != false) {
            // Success
            LogUtil::registerStatus($this->__('Done! Review created'));
        }

        // get and redirect to the right place
        $referer = System::serverGetVar('HTTP_REFERER');
        if (stristr($referer, 'type=admin')) {
            return System::redirect(ModUtil::url('Reviews', 'admin', 'view'));
        } else {
            return System::redirect(ModUtil::url('Reviews', 'user', 'view'));
        }
    }

    /**
     * view items
     *
     * @return string HTML output
     */
    public function view()
    {
        // Security check
        if (!SecurityUtil::checkPermission('Reviews::', '::', ACCESS_OVERVIEW)) {
            return LogUtil::registerPermissionError();
        }

        // Get parameters from whatever input we need
        $cat    = (string)FormUtil::getPassedValue('cat', isset($args['cat']) ? $args['cat'] : null, 'GET');
        $prop   = (string)FormUtil::getPassedValue('prop', isset($args['prop']) ? $args['prop'] : null, 'GET');
        $letter = (string)FormUtil::getPassedValue('letter', null, 'REQUEST');
        $page   = (int)FormUtil::getPassedValue('page', isset($args['page']) ? $args['page'] : 1, 'GET');

        // get all module vars for later use
        $modvars = ModUtil::getVar('Reviews');

        // defaults and input validation
        if (!is_numeric($page) || $page < 0) {
            $page = 1;
        }
        $startnum = (($page - 1) * $modvars['itemsperpage']) + 1;

        // check if categorisation is enabled
        if ($modvars['enablecategorization']) {
            // get the categories registered for Reviews
            $catregistry = CategoryRegistryUtil::getRegisteredModuleCategories('Reviews', 'reviews');
            $properties = array_keys($catregistry);

            // validate the property
            // and build the category filter - mateo
            if (!empty($properties) && in_array($prop, $properties)) {
                // if the property and the category are specified
                // means that we'll list the reviews that belongs to that category
                if (!empty($cat)) {
                    if (!is_numeric($cat)) {
                        $rootCat = CategoryUtil::getCategoryByID($catregistry[$prop]);
                        $cat = CategoryUtil::getCategoryByPath($rootCat['path'].'/'.$cat);
                    } else {
                        $cat = CategoryUtil::getCategoryByID($cat);
                    }
                    if (!empty($cat) && isset($cat['path'])) {
                        // include all it's subcategories and build the filter
                        $categories = categoryUtil::getCategoriesByPath($cat['path'], '', 'path');
                        $catstofilter = array();
                        foreach ($categories as $category) {
                            $catstofilter[] = $category['id'];
                        }
                        $catFilter = array($prop => $catstofilter);
                    } else {
                        LogUtil::registerError($this->__('Invalid category passed.'));
                    }
                }
            }
        }

        // Get all matching reviews
        $items = ModUtil::apiFunc('Reviews', 'user', 'getall',
                array('startnum' => $startnum,
                'numitems' => $modvars['itemsperpage'],
                'letter'   => $letter,
                'category' => isset($catFilter) ? $catFilter : null,
                'catregistry' => isset($catregistry) ? $catregistry : null));

        // assign all the necessary template variables
        $this->view->assign('items', $items);
        $this->view->assign('category', $cat);
        $this->view->assign('lang', ZLanguage::getLanguageCode());
        $this->view->assign($modvars);
        $this->view->assign('shorturls', System::getVar('shorturls'));
        $this->view->assign('shorturlstype', System::getVar('shorturlstype'));

        // Assign the values for the smarty plugin to produce a pager
        $this->view->assign('pager', array('numitems' => ModUtil::apiFunc('Reviews', 'user', 'countitems',
                array('letter' => $letter,
                'category' => isset($catFilter) ? $catFilter : null)),
                'itemsperpage' => $modvars['itemsperpage']));

        // Return the output that has been generated by this function
        return $this->view->fetch('reviews_user_view.htm');
    }

    /**
     * display item
     *
     * @return string HTML output
     */
    public function display($args)
    {
        $id       = FormUtil::getPassedValue('id', isset($args['id']) ? $args['id'] : null, 'REQUEST');
        $title    = FormUtil::getPassedValue('title', isset($args['title']) ? $args['title'] : null, 'REQUEST');
        $page     = FormUtil::getPassedValue('page', isset($args['page']) ? $args['page'] : 1, 'REQUEST');
        $objectid = FormUtil::getPassedValue('objectid', isset($args['objectid']) ? $args['objectid'] : null, 'REQUEST');
        if (!empty($objectid)) {
            $id = $objectid;
        }

        // Validate the essential parameters
        if ((empty($id) || !is_numeric($id)) && empty($title)) {
            return LogUtil::registerArgsError();
        }
        if (!empty($title)) {
            unset($id);
        }

        // increment the read count
        if ($page == 1) {
            if (isset($id)) {
                ModUtil::apiFunc('Reviews', 'user', 'incrementreadcount', array('id' => $id));
            } else {
                ModUtil::apiFunc('Reviews', 'user', 'incrementreadcount', array('title' => $title));
            }
        }

        // set the cache id
        if (isset($id)) {
            $this->view->cache_id = $id.$page;
        } else {
            $this->view->cache_id = $title.$page;
        }

        // check if the contents are cached.
        if ($this->view->is_cached('reviews_user_display.htm')) {
            return $this->view->fetch('reviews_user_display.htm');
        }

        // Get the review
        if (isset($id) && is_numeric($id)) {
            $item = ModUtil::apiFunc('Reviews', 'user', 'get', array('id' => $id));
        } else {
            $item = ModUtil::apiFunc('Reviews', 'user', 'get', array('title' => $title));
            System::queryStringSetVar('id', $item['id']);
        }

        if ($item === false) {
            return LogUtil::registerError($this->__('No such review found.'), 404);
        }

        // Explode the review into an array of seperate pages
        $allpages = explode('<!--pagebreak-->', $item['text']);
        unset($item['text']);

        // Set the item review to be the required page
        // nb arrays start from zero pages from one
        // check if the page does exists
        if (!isset($allpages[$page-1])) {
            return LogUtil::registerError($this->__('No such review page found.'), 404);
        }
        $item['text'] = $allpages[$page-1];
        $numpages = count($allpages);
        unset($allpages);

        if (!preg_match("/([\<])([^\>]{1,})*([\>])/i", $item['text'])) {
            $item['text'] = nl2br(trim($item['text']));
        }

        // Assign the item to the template
        $this->view->assign($item);

        // Now lets assign the informatation to create a pager for the review
        $this->view->assign('pager', array('numitems'     => $numpages,
                'itemsperpage' => 1));

        // Return the output that has been generated by this function
        return $this->view->fetch('reviews_user_display.htm');
    }
}