<?php


require_once dirname(__FILE__).'/../vendor/autoload.php';

class EtherpadManager
{
    private $etherpadConnector;

    public function __construct()
    {
        include_once dirname(__FILE__).'/../../../businesslogic/MongoDBDocumentManager.php';
        $this->documentManager = new MongoDBDocumentManager();
        $this->etherpadConnector = new EtherpadLite\Client('apikey', 'apiurl');
    }

    /*
     * Create etherpad
     */
    private function createEtherpad($documentId, $name)
    {
        if ($this->etherpadConnector) {
            $groupIdRequest = $this->etherpadConnector->createGroupIfNotExistsFor($documentId);
            $groupIdData = $groupIdRequest->getData();
            $groupId = $groupIdData['groupID'];

            $padIdRequest = $this->etherpadConnector->createGroupPad($groupId, $name);
            $padIdData = $padIdRequest->getData();
            $padId = $padIdData['padID'];

            return $padId;
        }
    }

    /*
     * Create etherpad
     */
    private function getEtherpadId($documentId, $fieldName)
    {
        if ($this->etherpadConnector) {


            return $padId;
        }
    }

    /*
     * create etherpad session for user
     */
    public function createSession($userId, $username, $documentId)
    {
        if ($this->etherpadConnector) {
            try {

                $authorIdRequest = $this->etherpadConnector->createAuthorIfNotExistsFor($userId, $username);
                $authorIdData = $authorIdRequest->getData();
                $authorId = $authorIdData['authorID'];

                $groupIdRequest = $this->etherpadConnector->createGroupIfNotExistsFor($documentId);
                $groupIdData = $groupIdRequest->getData();
                $groupId = $groupIdData['groupID'];

                $sessionIdRequest = $this->etherpadConnector->createSession($groupId, $authorId, time() + (3 * 60 * 60));
                $sessionIdData = $sessionIdRequest->getData();
                $sessionId = $sessionIdData['sessionID'];
            } catch (Exception $e) {
                return;
            }
        }

        return $sessionId;
    }

    /**
     * Get etherpad html.
     */
    public function getEtherpadHTML($padId)
    {
        $html = '';

        if ($this->etherpadConnector) {
            $htmlRequest = $this->etherpadConnector->getHTML($padId);
            $htmlData = $htmlRequest->getData();
            $html = $htmlData['html'];
        }

        return $html;
    }

    /**
     * Remove etherpad.
     */
    public function removeEtherpad($padId)
    {
        if ($this->etherpadConnector) {
            $this->etherpadConnector->deletePad($padId);
        }

        return;
    }
}
