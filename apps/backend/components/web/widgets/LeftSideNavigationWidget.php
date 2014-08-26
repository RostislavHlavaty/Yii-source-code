<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * LeftSideNavigationWidget
 * 
 * @package MailWizz EMA
 * @author Serban George Cristian <cristian.serban@mailwizz.com> 
 * @link http://www.mailwizz.com/
 * @copyright 2013-2014 MailWizz EMA (http://www.mailwizz.com)
 * @license http://www.mailwizz.com/license/
 * @since 1.0
 */
 
class LeftSideNavigationWidget extends CWidget
{
    public function run()
    {
        $sections   = array();
        $hooks      = Yii::app()->hooks;
        $controller = $this->controller;
        $route      = $controller->route;
        $priority   = 0;
        $request    = Yii::app()->request;
        
        Yii::import('zii.widgets.CMenu');
        
        $menuItems = array(
            'dashboard' => array(
                'name'      => Yii::t('app', 'Dashboard'),
                'icon'      => 'glyphicon-dashboard',
                'active'    => 'dashboard',
                'route'     => array('dashboard/index'),
            ),
	    /* HIDDING Articles
            'articles' => array(
                'name'      => Yii::t('app', 'Articles'),
                'icon'      => 'glyphicon-book',
                'active'    => 'article',
                'route'     => null,
                'items'     => array(
                    array('url' => array('articles/index'), 'label' => Yii::t('app', 'View all articles'), 'active' => strpos($route, 'articles/index') === 0),
                    array('url' => array('article_categories/index'), 'label' => Yii::t('app', 'View all categories'), 'active' => strpos($route, 'article_categories') === 0),
                ),
            ),
	    */
	    'misc' => array(
			    'name'      => Yii::t('app', 'Data Filters'),
			    'icon'      => 'glyphicon-bookmark',
			    'active'    => 'misc',
			    'route'     => null,
			    'items'     => array(
						 /*
						   array('url' => array('misc/emergency_actions'), 'label' => Yii::t('app', 'Emergency actions'), 'active' => strpos($route, 'misc/emergency_actions') === 0),
						   array('url' => array('misc/application_log'), 'label' => Yii::t('app', 'Application log'), 'active' => strpos($route, 'misc/application_log') === 0),
						 */
						 array('url' => array('misc/filter_actions'), 'label' => Yii::t('app', 'Filter actions'), 'active' => strpos($route, 'misc/filter_actions') === 0),
						 ),
	    ),
	    'customers' => array(
				 'name'      => Yii::t('app', 'Customers'),
				 'icon'      => 'glyphicon-user',
				 'active'    => 'customer',
				 'route'     => null,
				 'items'     => array(
						      array('url' => array('customers/index'), 'label' => Yii::t('app', 'Customers'), 'active' => strpos($route, 'customers') === 0),
						      array('url' => array('customer_groups/index'), 'label' => Yii::t('app', 'Groups'), 'active' => strpos($route, 'customer_groups') === 0),
						      ),
	    ),
            'users' => array(
                'name'      => Yii::t('app', 'Users'),
                'icon'      => 'glyphicon-user',
                'active'    => 'users',
                'route'     => array('users/index'),
            ),
            'monetization' => array(
                'name'      => Yii::t('app', 'Monetization'),
                'icon'      => 'glyphicon-credit-card',
                'active'    => array('payment_gateway', 'price_plans', 'orders', 'promo_codes', 'currencies', 'taxes'),
                'route'     => null,
                'items'     => array(
                    array('url' => array('payment_gateways/index'), 'label' => Yii::t('app', 'Payment gateways'), 'active' => strpos($route, 'payment_gateway') === 0),
                    array('url' => array('price_plans/index'), 'label' => Yii::t('app', 'Price plans'), 'active' => strpos($route, 'price_plans') === 0),
                    array('url' => array('orders/index'), 'label' => Yii::t('app', 'Orders'), 'active' => strpos($route, 'orders') === 0),
                    array('url' => array('promo_codes/index'), 'label' => Yii::t('app', 'Promo codes'), 'active' => strpos($route, 'promo_codes') === 0),
                    array('url' => array('currencies/index'), 'label' => Yii::t('app', 'Currencies'), 'active' => strpos($route, 'currencies') === 0),
                    array('url' => array('taxes/index'), 'label' => Yii::t('app', 'Taxes'), 'active' => strpos($route, 'taxes') === 0),
                ),
            ),
	    /*
            'customers' => array(
                'name'      => Yii::t('app', 'Customers'),
                'icon'      => 'glyphicon-user',
                'active'    => 'customer',
                'route'     => null,
                'items'     => array(
                    array('url' => array('customers/index'), 'label' => Yii::t('app', 'Customers'), 'active' => strpos($route, 'customers') === 0),
                    array('url' => array('customer_groups/index'), 'label' => Yii::t('app', 'Groups'), 'active' => strpos($route, 'customer_groups') === 0),
                ),
            ),
	    */
	    /* HIDDING Servers
            'servers'       => array(
                'name'      => Yii::t('app', 'Servers'),
                'icon'      => 'glyphicon-transfer',
                'active'    => array('delivery_servers', 'bounce_servers', 'feedback_loop_servers'),
                'route'     => null,
                'items'     => array(
                    array('url' => array('delivery_servers/index'), 'label' => Yii::t('app', 'Delivery servers'), 'active' => strpos($route, 'delivery_servers') === 0),
                    array('url' => array('bounce_servers/index'), 'label' => Yii::t('app', 'Bounce servers'), 'active' => strpos($route, 'bounce_servers') === 0),
                    array('url' => array('feedback_loop_servers/index'), 'label' => Yii::t('app', 'Feedback loop servers'), 'active' => strpos($route, 'feedback_loop_servers') === 0),
                ),
            ),
	    */
            'list-page-type' => array(
                'name'      => Yii::t('app', 'List page types'),
                'icon'      => 'glyphicon-list-alt',
                'active'    => 'list_page_type',
                'route'     => array('list_page_type/index'),
            ),
	    /* HIDDING Email blacklist
            'blacklist' => array(
                'name'      => Yii::t('app', 'Email blacklist'),
                'icon'      => 'glyphicon-ban-circle',
                'active'    => 'email_blacklist',
                'route'     => array('email_blacklist/index'),
            ),
	    */
	    /* HIDDING Extend
            'extend' => array(
                'name'      => Yii::t('app', 'Extend'),
                'icon'      => 'glyphicon-plus-sign',
                'active'    => array('extensions', 'theme', 'languages', 'ext'),
                'route'     => null,
                'items'     => array(
                    array('url' => array('extensions/index'), 'label' => Yii::t('app', 'Extensions'), 'active' => strpos($route, 'ext') === 0),
                    array('url' => array('theme/index'), 'label' => Yii::t('app', 'Themes'), 'active' => strpos($route, 'theme') === 0),
                    array('url' => array('languages/index'), 'label' => Yii::t('app', 'Languages'), 'active' => strpos($route, 'languages') === 0),
                ),
            ),
	    */
            /* HIDDING Locations
            'locations' => array(
                'name'      => Yii::t('app', 'Locations'),
                'icon'      => 'glyphicon-globe',
                'active'    => array('ip_location_services', 'countries', 'zones'),
                'route'     => null,
                'items'     => array(
                    array('url' => array('ip_location_services/index'), 'label' => Yii::t('app', 'Ip location services'), 'active' => strpos($route, 'ip_location_services') === 0),
                    array('url' => array('countries/index'), 'label' => Yii::t('app', 'Countries'), 'active' => strpos($route, 'countries') === 0),
                    array('url' => array('zones/index'), 'label' => Yii::t('app', 'Zones'), 'active' => strpos($route, 'zones') === 0),
                ),
            ),
	    */
            'settings' => array(
                'name'      => Yii::t('app', 'Settings'),
                'icon'      => 'glyphicon-cog',
                'active'    => 'settings',
                'route'     => null,
                'items'     => array(
                    array('url' => array('settings/index'), 'label' => Yii::t('app', 'Common'), 'active' => strpos($route, 'settings/index') === 0),
                    array('url' => array('settings/import_export'), 'label' => Yii::t('app', 'Import/Export'), 'active' => strpos($route, 'settings/import_export') === 0),
                    array('url' => array('settings/email_templates'), 'label' => Yii::t('app', 'Email templates'), 'active' => strpos($route, 'settings/email_templates') === 0),
                    array('url' => array('settings/cron'), 'label' => Yii::t('app', 'Cron'), 'active' => strpos($route, 'settings/cron') === 0),
                    array('url' => array('settings/email_blacklist'), 'label' => Yii::t('app', 'Email blacklist'), 'active' => strpos($route, 'settings/email_blacklist') === 0),
                    array('url' => array('settings/campaign_attachments'), 'label' => Yii::t('app', 'Campaigns'), 'active' => strpos($route, 'settings/campaign_') === 0),
                    array('url' => array('settings/customer_servers'), 'label' => Yii::t('app', 'Customers'), 'active' => strpos($route, 'settings/customer_') === 0),
                    array('url' => array('settings/monetization'), 'label' => Yii::t('app', 'Monetization'), 'active' => strpos($route, 'settings/monetization') === 0),
                ),
				),
        );
        
        $menuItems = (array)Yii::app()->hooks->applyFilters('backend_left_navigation_menu_items', $menuItems);
        
        $menu = new CMenu();
        $menu->htmlOptions          = array('class' => 'sidebar-menu');
        $menu->submenuHtmlOptions   = array('class' => 'treeview-menu');
        
        foreach ($menuItems as $key => $data) {
            $_route  = !empty($data['route']) ? $data['route'] : 'javascript:;';
            $active  = false;
            
            if (is_string($data['active']) && strpos($route, $data['active']) === 0) {
                $active = true;
            } elseif (is_array($data['active'])) {
                foreach ($data['active'] as $in) {
                    if (strpos($route, $in) === 0) {
                        $active = true;
                        break;
                    }
                }
            }
            
            $item = array(
                'url'       => $_route, 
                'label'     => '<i class="glyphicon '.$data['icon'].'"></i> <span>'.$data['name'].'</span>' . (!empty($data['items']) ? '<i class="fa fa-angle-left pull-right"></i>' : ''), 
                'active'    => $active
            );
            
            if (!empty($data['items'])) {
                foreach ($data['items'] as $index => $i) {
                    if (isset($i['label'])) {
                        $data['items'][$index]['label'] = '<i class="fa fa-angle-double-right"></i>' . $i['label'];
                    }
                }
                $item['items']       = $data['items'];
                $item['itemOptions'] = array('class' => 'treeview');
            }
            
            $menu->items[] = $item;
        }

        $menu->run();
    }
}