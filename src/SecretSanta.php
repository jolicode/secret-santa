<?php

namespace Joli\SlackSecretSanta;

class SecretSanta
{
    /** @var string */
    private $hash;

    /** @var array */
    private $associations;

    /** @var array */
    private $remainingAssociations;

    /** @var string|null */
    private $adminUserId;

    /** @var string|null */
    private $adminMessage;

    /** @var string|null */
    private $error;

    /**
     * @param string $hash
     * @param array  $associations
     * @param string $adminMessage
     * @param string $adminUserId
     */
    public function __construct($hash, array $associations, $adminUserId, $adminMessage = null)
    {
        $this->hash = $hash;
        $this->associations = $associations;
        $this->remainingAssociations = $associations;
        $this->adminUserId = $adminUserId;
        $this->adminMessage = $adminMessage;
    }

    /**
     * @return string
     */
    public function getHash()
    {
        return $this->hash;
    }

    /**
     * @return string[]
     */
    public function getAssociations()
    {
        return $this->associations;
    }

    /**
     * @return string[]
     */
    public function getRemainingAssociations()
    {
        return $this->remainingAssociations;
    }

    /**
     * @return string|null
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * @return string
     */
    public function getAdminUserId()
    {
        return $this->adminUserId;
    }

    /**
     * @return string
     */
    public function getAdminMessage()
    {
        return $this->adminMessage;
    }

    /**
     * @return bool
     */
    public function isDone()
    {
        return empty($this->remainingAssociations);
    }

    /**
     * @param string $giver
     */
    public function markAssociationAsProceeded($giver)
    {
        unset($this->remainingAssociations[$giver]);
    }

    /**
     * @param string $error
     */
    public function setError($error)
    {
        $this->error = $error;
    }
}
