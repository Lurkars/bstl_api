<?php

class AuthenticationManager
{
    private $documentManager;
     //Constructor
    public function __construct()
    {
        include_once dirname(__FILE__).'/MongoDBDocumentManager.php';
        $this->documentManager = new MongoDBDocumentManager();
    }

    public function authenticate($loginname, $password)
    {
        $loginname = strtolower($loginname);

        $authentication = $this->documentManager->findOne(null, array('data.loginname' => $loginname, 'interfaces' => array('$in' => array('authentication'))), array(),  false);

        if (!isset($authentication)) {
            return;
        }

        if (isset($authentication) && isset($authentication['data']) && isset($authentication['data']['password']) && password_verify($password, $authentication['data']['password'])) {
            $_SESSION['userId'] = $authentication['id'];

            $authentication['lastlogin'] = time() * 1000;
            unset($authentication['data']['password']);

            $authentication = $this->documentManager->updateDocument($authentication['id'], $authentication);

            return $authentication;
        }

        return;
    }
}
