<?php

namespace Joli\SlackSecretSanta;

class Result
{
    /** @var string */
    private $hash;

    /** @var array */
    private $associations;

    /** @var array */
    private $remainingAssociations;

    /** @var string|null */
    private $error;

    /**
     * @param string      $hash
     * @param array       $associations
     * @param array       $remainingAssociations
     * @param string|null $error
     */
    public function __construct($hash, array $associations, array $remainingAssociations, $error = null)
    {
        $this->hash = $hash;
        $this->associations = $associations;
        $this->remainingAssociations = $remainingAssociations;
        $this->error = $error;
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
     * @return bool
     */
    public function isOk()
    {
        return empty($this->remainingAssociations);
    }
}
