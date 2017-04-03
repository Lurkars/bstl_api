<?php

class AuthController
{
    private $authManager;
   //Constructor
   public function __construct()
   {
       include_once dirname(__FILE__).'/../businesslogic/AuthenticationManager.php';
       include_once dirname(__FILE__).'/../businesslogic/MongoDBDocumentManager.php';
       $this->authManager = new AuthenticationManager();
       $this->documentManager = new MongoDBDocumentManager();
   }

    /**
     * get current authorized userid
     */
    public function getAuth($request, $response, $args)
    {
        $userId = $request->getAttribute('userId');

        if (!is_null($userId)) {
            $authentication = $this->documentManager->getDocument($userId, $userId, true);
            return $response->write(json_encode($authentication));
        }

        return $response->withStatus(401);
    }

    /**
     * login with loginname & password.
     */
    public function login($request, $response, $args)
    {
        $loginData = json_decode($request->getBody());

        if (!isset($loginData->loginname) || !isset($loginData->password)) {
            return $response->withStatus(422);
        }

        $loginname = $loginData->loginname;
        $password = $loginData->password;
        $authentication = $this->authManager->authenticate($loginname, $password);
        if (!is_null($authentication)) {
            return $response->write(json_encode($authentication));
        }

        return $response->withStatus(401);
    }

    /**
     * logout.
     */
    public function logout($request, $response, $args)
    {
          $userId = $request->getAttribute('userId');

          if (!is_null($userId)) {
              $authentication = $this->documentManager->getDocument($userId, $userId);
              $authentication['lastlogout'] = time() * 1000;
              $authentication =  $this->documentManager->updateDocument($userId, $authentication);
          }

        session_destroy();

        return $response->withStatus(200);
    }
}

// get current auth: /auth
$app->get('/auth', '\AuthController:getAuth');

// login: /auth/login
$app->post('/auth/login', '\AuthController:login');

// logout: /auth/logout
$app->get('/auth/logout', '\AuthController:logout')->add($requireAuthorization);
$app->post('/auth/logout', '\AuthController:logout')->add($requireAuthorization);
