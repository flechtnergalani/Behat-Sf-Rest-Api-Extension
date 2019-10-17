<?php


namespace Atamis\Extension\SFDBextension\Initializer;

use Atamis\Extension\SFDBextension\SalesforceDatabaseConnection;
use Behat\Behat\Context\Context;
use Behat\Behat\Context\Initializer\ContextInitializer;
use Atamis\Extension\SFDBextension\Interfaces\SalesforceDBAware;

class SFDBInitializer implements ContextInitializer
{
    private $connection;
    
    public function __construct($user, $pw, $token, $wsdl_path, $endpoint)
    {
        $this->connection = new SalesforceDatabaseConnection($user, $pw, $token, $wsdl_path, $endpoint);
    }
    
    public function initializeContext(Context $context)
    {
        if (!$this->supportsContext($context)) {
            return;
        }
        $context->setConnection($this->connection);
    }

    public function supportsContext(Context $context)
    {
        return $context instanceof SalesforceDBAware;
    }
}
