<?php

include_once 'vue_home.php';
include_once 'modele_home.php';

class ControllerHome
{
    private $action;
    private $vue;
    private $modele;

    public function __construct()
    {
        $this->action = $_GET['action'] ?? "welcome";
        $this->vue = new VueHome();
        $this->modele = new ModHome();
    }

    public function getAction()
    {
        return $this->action;
    }

    public function welcome()
    {
      $result = $this->modele->recupereTop3();
        
        $this->vue->welcome($result);
    }

    public function bonsPlans()
    {
        $this->vue->bonsPlans();
    }

    public function events()
    {
        $this->vue->events();
    }

    public function notPermitted() {
        $this->vue->notPermitted();
    }
    
    public function restauration()
    {
        $this->vue->restauration();
    }

    public function displayContent()
    {
        return $this->vue->getAffichage();
    }
}