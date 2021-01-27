<?php
/**
 * Interworx API class
 *
 * @author João Cagnoni <joao@clientexec.com>
 *
 * @todo Support authentication by user/password
 * @todo There are a lot of remaining methods
 */
class InterworxApi
{
    /**
     * SoapClient
     */
    protected $_client;

    /**
     * Access key
     */
    protected $_key;

    function __construct($ip, $key)
    {
        $this->setKey($key);
        $this->_connect($ip);
    }

    /**
     * Connect to the SOAP server
     *
     * @param string $ip IP Address
     *
     * @return void
     */
    protected function _connect($ip)
    {
        $caPathOrFile = \Composer\CaBundle\CaBundle::getSystemCaRootBundlePath();
        if (is_dir($caPathOrFile)) {
            $opts['ssl']['capath'] = $caPathOrFile;
        } else {
            $opts['ssl']['cafile'] = $caPathOrFile;
        }

        $options = [
        'stream_context' => stream_context_create($opts)
        ];

        $this->_client = new SoapClient("https://{$ip}:2443/soap?wsdl", $options);
    }

    /**
     * Add a new siteworx account
     *
     * @param array $data Input data
     *
     * @return mixed
     */
    public function addSiteworxAccount($data)
    {
        $result = $this->call('/nodeworx/siteworx', 'add', $data);
        return $result;
    }


    /**
     * Add a new reseller account
     *
     * @param array $data Input data
     *
     * @return mixed
     */
    public function addResellerAccount($data)
    {
        $result = $this->call('/nodeworx/reseller', 'add', $data);
        return $result;
    }

    /**
     * Call the soap server
     *
     * @param string $controller The requested controller
     * @param string $action     The requested action
     * @param array  $input      Input data
     *
     * @return mixed
     */
    public function call($controller, $action, $input = null)
    {
        $result = $this->_client->route($this->_key, $controller, $action, $input);

        if (!is_array($result) or (is_array($result) and (!array_key_exists('status', $result) or !array_key_exists('payload', $result)))) {
            $error = 'Unexpected response from Interworx Server.';
            CE_Lib::log(4, "InterworxApi::call::error: ({$status}) Result:\n" . print_r($result, true));
            throw new CE_Exception($error);
        }

        $status = $result['status'];
        $payload = $result['payload'];

        if ($status == 401) {
            $error = 'Failed to authenticate.';
            CE_Lib::log(4, "InterworxApi::call::error: {$error}");
            throw new CE_Exception($error);
        } elseif ($status != 0) {
            if (is_array($payload)) {
                $error = 'Failed to call the Interworx API.';
                CE_Lib::log(4, "InterworxApi::call::error: ({$status}) Result:\n" . print_r($payload, true));
                throw new CE_Exception($error);
            } elseif (empty($payload)) {
                $error = "The result is empty.";
                CE_Lib::log(4, "InterworxApi::call::error: ({$status}) {$error}");
                throw new CE_Exception($error);
            } else {
                $error = $payload;
                CE_Lib::log(4, "InterworxApi::call::error: ({$status}) {$error}");
                throw new CE_Exception($error);
            }
        }

        return $payload;
    }

    /**
     * Delete a reseller account
     *
     * @param string $resellerId Reseller ID
     *
     * @return mixed
     */
    public function deleteResellerAccount($resellerId)
    {
        $data = [
            'reseller_id' => $resellerId
        ];
        $result = $this->call('/nodeworx/reseller', 'delete', $data);
        return $result;
    }

    /**
     * Delete a siteworx account
     *
     * @param string $domain Domain name
     *
     * @return mixed
     */
    public function deleteSiteworxAccount($domain)
    {
        $data = array('domain' => $domain);
        $result = $this->call('/nodeworx/siteworx', 'delete', $data);
        return $result;
    }

    /**
     * Edit a siteworx account
     *
     * @param array $data Input data
     *
     * @return mixed
     */
    public function editSiteworxAccount($data)
    {
        $result = $this->call('/nodeworx/siteworx', 'edit', $data);
        return $result;
    }

    /**
     * Get siteworx accout details
     *
     * @param string $domain Domain name
     *
     * @return mixed
     */
    public function getSiteworxAccount($domain)
    {
        $data = array('domain' => $domain);
        $result = $this->call('/nodeworx/siteworx', 'querySiteworxAccountDetails', $data);
        return $result;
    }

    /**
     * List the packages on the server
     *
     * @return array
     */
    public function listPackages()
    {
        $packages = $this->call('/nodeworx/packages', 'listDetails');

        return $packages;
    }

    /**
     * Check if the package exists
     *
     * @param string $name Name of the package on the server
     *
     * @return bool
     */
    public function packageExists($name)
    {
        $packages = $this->listPackages();
        $packageExists = false;

        foreach ($packages as $package) {
            if ($package['name'] == $name) {
                $packageExists = true;
            }
        }

        return $packageExists;
    }

    /**
     * Set the access key
     *
     * @param string $key Access key of the server. It can also be an active session ID.
     */
    public function setKey($key)
    {
        $this->_key = $key;
    }

    /**
     * Suspend a siteworx account
     *
     * @param string $domain Domain name
     *
     * @return mixed
     */
    public function suspendSiteworxAccount($domain)
    {
        $data = array('domain' => $domain);
        $result = $this->call('/nodeworx/siteworx', 'suspend', $data);
        return $result;
    }

    /**
     * Suspend a reseler account
     *
     * @param string $resellerId Domain name
     *
     * @return mixed
     */
    public function suspendResellerAccount($resellerId)
    {
        $data = [
            'reseller_id' => $resellerId,
            'status' => 'inactive'
        ];
        $result = $this->call('/nodeworx/reseller', 'edit', $data);
        return $result;
    }

    /**
     * Unsuspend a siteworx account
     *
     * @param string $domain Domain name
     *
     * @return mixed
     */
    public function unsuspendSiteworxAccount($domain)
    {
        $data = array('domain' => $domain);
        $result = $this->call('/nodeworx/siteworx', 'unsuspend', $data);
        return $result;
    }

    /**
     * Unsuspend a reseller account
     *
     * @param string $resellerId Domain name
     *
     * @return mixed
     */
    public function unsuspendResellerAccount($resellerId)
    {
        $data = [
            'reseller_id' => $resellerId,
            'status' => 'active'
        ];
        $result = $this->call('/nodeworx/reseller', 'edit', $data);
        return $result;
    }


    /**
     * Get the Reseller ID
     *
     * @param string $email Reseller's Email Address
     *
     * @return mixed
     */
    public function getResellerId($email)
    {
        $result = $this->call('/nodeworx/reseller', 'listIds', []);
        foreach ($result as $reseller) {
            list($resellerID, $resellerEmail) = $reseller;
            $resellerEmail = explode("(", $resellerEmail, 2);
            $resellerEmail = $resellerEmail[1];
            $resellerEmail = substr($resellerEmail, 0, -1);
            if ($resellerEmail == $email) {
                return $resellerID;
            }
        }
    }

    public function queryResellerDetails($email)
    {
        $data = [
        //'reseller' => $email
        ];
        // Due to a bug with intewrox, we need to call list, instead of queryResellerDetails.
        $result = $this->call('/nodeworx/reseller', 'listResellers', $data);
        foreach ($result as $account) {
            if ($account->email == $email) {
                return $account;
            }
        }
        throw new Exception('Reseller does not exist');
    }

    public function getFreeIP()
    {
        $result = $this->call('/nodeworx/siteworx', 'listFreeIps', []);
        return $result[0][0];
    }
}
