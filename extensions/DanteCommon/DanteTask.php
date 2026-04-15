<?php





interface DanteTask {

  public function execute ();
  public function executeAndStream ();



}




class DanteShellTask implements DanteTask {

public function execute () {}
public function executeAndStream(){}

}



class DantePHPTask implements DanteTask {


public function __construct (private callable $fct, private array $args, private float $timeOut) { }

public function execute () {} 

public function executeAndStream() {



}



}

















?>