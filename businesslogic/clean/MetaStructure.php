<?php


class MetaStructure implements DocumentCleanUpInterface
{
    private $documentManager;
     //Constructor
    public function __construct()
    {
        include_once dirname(__FILE__).'/MongoDBDocumentManager.php';
        $this->documentManager = new MongoDBDocumentManager();
    }

    public function cleanUp($userId, $document)
    {
        return $this->cleanUpInternal($userId, $document);
    }

    private function cleanUpInternal($userId, $document, $interfaces = array())
    {

        print_r($userId);
        if (!isset($document['meta'])) {
            $document['meta'] = array();
        }

        // apply meta structure
        $document['meta']['structure'] = array($document['id'] => $document['structure']);

        if (isset($document['interfaces']) && is_array($document['interfaces'])) {
            $document['meta']['interfaces'] = array();
            foreach ($document['interfaces'] as $interface) {
                if (!in_array($interface, $interfaces) && !in_array($interface, $document['meta']['interfaces'])) {
                    array_push($document['meta']['interfaces'], $interface);

                // recursive structure
                $type = $this->cleanUpInternal($userId, $this->$documentManager->findOne(array('id' => $interface)), $document['meta']['interfaces']);
                    if (isset($type['meta'])) {
                        if (isset($type['meta']['structure'])) {
                            $document['meta']['structure'] = array_merge($type['meta']['structure'], $document['meta']['structure']);
                        }
                        if (isset($type['meta']['interfaces'])) {
                            $document['meta']['interfaces'] = array_unique(array_merge($type['meta']['interfaces'], $document['meta']['interfaces']));
                        }
                    }
                }
            }
        }

        return $document;
    }
}
