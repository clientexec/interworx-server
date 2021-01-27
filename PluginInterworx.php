<?php
require_once 'modules/admin/models/ServerPlugin.php';
require_once 'plugins/server/interworx/InterworxApi.php';

/**
 * Interworx Plugin
 *
 * @author João Cagnoni <joao@clientexec.com>
 *
 * @package Plugins
 *
 * @todo Update method
 * @todo Resellers supporting
 */
class PluginInterworx extends ServerPlugin
{
    public $features = array(
        'packageName' => true,
        'testConnection' => true,
        'showNameservers' => true,
        'upgrades' => true
    );

    /**
     * Process vars that is used on some method.
     * This method exists only to prevent duplicated code.
     *
     * @param array $args Arguments
     *
     * @return array
     */
    protected function _setup($args)
    {
        if ($args instanceof UserPackage) {
            $userPackage = $args;
        } else {
            $userPackage = new UserPackage($args['userPackageId']);
        }

        $params = $this->buildParams($userPackage);
        $serverHostname = $params['server']['variables']['ServerHostName'];
        $serverKey = $params['server']['variables']['plugin_interworx_Access_Key'];
        $api = new InterworxApi($serverHostname, $serverKey);
        $domainName = $userPackage->getCustomField('Domain Name');
        $isReseller = (isset($params['package']['is_reseller']) && $params['package']['is_reseller'] == 1 ? true : false);
        $data = array($userPackage, $params, $serverHostname, $serverKey, $api, $domainName, $isReseller);

        return $data;
    }

    /**
     * Create a new account
     *
     * @param array $args Array of available arguments/variables
     *
     * @return string
     */
    public function doCreate($args)
    {
        list($userPackage, $params, $serverHostname, $serverKey, $api, $domainName, $isReseller) = $this->_setup($args);
        $data = array(
            'domainname' => $params['package']['domain_name'],
            'ipaddress' => $params['package']['ip'],
            'database_server' => 'localhost',
            'billing_day' => date('j'),
            'uniqname' => (strlen($params['package']['username']) > 8) ? substr($params['package']['username'], 0, 8) : $params['package']['username'],
            'nickname' => "{$params['customer']['first_name']} {$params['customer']['last_name']}",
            'email' => $params['customer']['email'],
            'password' => $params['package']['password'],
            'confirm_password' => $params['package']['password'],
            'language' => 'en-us',
            'theme' => 'interworx',
            'menu_style' => 'small',
            'packagetemplate' => $params['package']['name_on_server']
        );

        if ($isReseller) {
            $ip = $api->getFreeIP();
            $data['status'] = 'active';
            $data['ipv4'] = $ip;
            $api->addResellerAccount($data);
        } else {
            $api->addSiteworxAccount($data);
        }


        return "{$domainName} has been created.";
    }

    /**
     * Delete an account
     *
     * @param array $args Array of available arguments/variables
     *
     * @return string
     */
    public function doDelete($args)
    {
        list($userPackage, $params, $serverHostname, $serverKey, $api, $domainName, $isReseller) = $this->_setup($args);
        if ($isReseller) {
            $resellerId = $api->getResellerId($params['customer']['email']);
            $api->deleteResellerAccount($resellerId);
        } else {
            $api->deleteSiteworxAccount($params['package']['domain_name']);
        }

        return "{$domainName} has been deleted.";
    }

    /**
     * Suspend an account
     *
     * @param array $args Array of available arguments/variables
     *
     * @return string
     */
    public function doSuspend($args)
    {
        list($userPackage, $params, $serverHostname, $serverKey, $api, $domainName, $isReseller) = $this->_setup($args);
        if ($isReseller) {
            $resellerId = $api->getResellerId($params['customer']['email']);
            $api->suspendResellerAccount($resellerId);
        } else {
            $api->suspendSiteworxAccount($params['package']['domain_name']);
        }
        return "{$domainName} has been suspended.";
    }

    /**
     * Update an account
     *
     * @param array $args Array of available arguments/variables
     *
     * @return string
     */
    public function doUpdate($args)
    {
        list($userPackage, $params, $serverHostname, $serverKey, $api, $domainName) = $this->_setup($args);
        $data = array(
            'domainname' => $params['package']['domain_name'],
            'ipaddress' => $params['package']['ip'],
            'uniqname' => (strlen($params['package']['username']) > 8) ? substr($params['package']['username'], 0, 8) : $params['package']['username'],
            'password' => $params['package']['password'],
            'confirm_password' => $params['package']['password'],
            'packagetemplate' => $params['package']['name_on_server']
        );
        $api->editSiteworxAccount($data);
        return "{$domainName} has been updated.";
    }

    /**
     * Unsuspend an account
     *
     * @param array $args Array of available arguments/variables
     *
     * @return string
     */
    public function doUnSuspend($args)
    {
        list($userPackage, $params, $serverHostname, $serverKey, $api, $domainName, $isReseller) = $this->_setup($args);
        if ($isReseller) {
            $resellerId = $api->getResellerId($params['customer']['email']);
            $api->unsuspendResellerAccount($resellerId);
        } else {
            $api->unsuspendSiteworxAccount($params['package']['domain_name']);
        }
        return "{$domainName} has been unsuspended.";
    }

    /**
     * Get the available actions based on the current status of the account
     *
     * @param UserPackage $userPackage User package
     *
     * @return array
     */
    public function getAvailableActions($userPackage)
    {
        list(, $params, $serverHostname, $serverKey, $api, $domainName, $isReseller) = $this->_setup($userPackage);
        $actions = array();

        try {
            if ($isReseller) {
                $account = $api->queryResellerDetails($params['customer']['email']);
                $actions[] = 'Delete';

                if ($account->status == 'active') {
                    $actions[] = 'Suspend';
                } else {
                    $actions[] = 'UnSuspend';
                }
            } else {
                $account = $api->getSiteworxAccount($domainName);
                $actions[] = 'Delete';

                if ($account['status'] == 'suspended' || $account['status'] == 'inactive') {
                    $actions[] = 'UnSuspend';
                } else {
                    $actions[] = 'Suspend';
                }
            }
        } catch (Exception $e) {
            $actions[] = 'Create';
        }

        return $actions;
    }

    /**
     * This function outlines variables used when setting up the plugin in the Servers section of ClientExec. It is a
     * required function.
     *
     * @return array
     */
    public function getVariables()
    {
        $variables = [
            lang('Name') => [
                'type' => 'hidden',
                'description' => lang('Used by ClientExec to display plugin. It must match the action function name(s).'),
                'value' => 'InterWorx-CP'
            ],
            lang('Description') => [
                'type' => 'hidden',
                'description'=> lang('Description viewable by admin in server settings'),
                'value' => lang('InterWorx-CP integration.')
            ],
            lang('Access Key') => [
                'type' => 'textarea',
                'description' => lang('Access key used to authenticate to server.'),
                'value' => '',
                'encryptable' => true
            ],
            lang('Actions') => [
                'type' => 'hidden',
                'description' => lang('Actions currently available for this plugin.'),
                'value' => 'Create,Delete,Suspend,UnSuspend'
            ],
            lang('reseller') => [
                'type' => 'hidden',
                'description' => lang('Whether this server plugin can set reseller accounts'),
                'value' => '1',
            ]
        ];
        return $variables;
    }

    public function testConnection($args)
    {
        CE_Lib::log(4, 'Testing connection to Interworx server');

        $api = new InterworxApi(
            $args['server']['variables']['ServerHostName'],
            $args['server']['variables']['plugin_interworx_Access_Key']
        );

        $response = $api->listPackages();
        if (!is_array($response)) {
            throw new CE_Exception("Connection to server failed.");
        }
    }
}
