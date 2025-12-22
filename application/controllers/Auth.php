<?php

class AuthController extends ApiController {

    public function loginAction() {
        $protocolType = $this->getRequest()->get('protocol', 'internal');
        $authAction = $this->getAuthWorker($protocolType);

        if ($authAction) {
            $result = $authAction->login();
            if (is_array($result)) {
                $this->setOutput($result);
                
            }
        } else {
            header("HTTP/1.0 400 Bad Request");
            die("Authentication protocol '{$protocolType}' is not supported.");
        }
    }

    public function logoutAction() {
        $protocolType = $this->getRequest()->get('protocol', 'internal');
        $authAction = $this->getAuthWorker($protocolType);
        
        if ($authAction) {
            $result = $authAction->logout();
            $this->setOutput($result);
        }

    }

    protected function getAuthWorker($type) {
        $cleanType = preg_replace('/[^a-zA-Z0-9]/', '', $type);
        $className = 'Billrun_Auth_' . ucfirst($cleanType);

        if (class_exists($className)) {
            return new $className($this->getRequest());
        }

        return null;
    }

    public function optionsAction()
    {
        $allowedProtocols = Billrun_Factory::config()->getConfigValue('auth.protocols', []);
        $options = [];

        foreach ($allowedProtocols as $protocol) {
            $providers = Billrun_Factory::config()->getConfigValue('auth.' . strtolower($protocol), []);
            if (is_array($providers)) {
                foreach ($providers as $provider) {
                    $options[] = [
                        'type' => $protocol,
                        'label' => isset($provider['label']) ? $provider['label'] : $protocol,
                        'name' => isset($provider['name']) ? $provider['name'] : uniqid($protocol . '_'),
                    ];
                }
            }
        }

        $this->setOutput(array(
            'status' => 1,
            'desc' => 'success',
            'details' => array(
                'protocols' => $options
            )
        ));
    }

}