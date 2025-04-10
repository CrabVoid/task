<?php

class Auto
{
    public $marka;
    public $modelis;
    public $gads;

    public function __construct($marka, $modelis, $gads)
    {
        $this->marka = $marka;
        $this->modelis = $modelis;
        $this->gads = $gads;
    }

    function showinfo()
    {
        return $this->marka . $this->modelis . $this->gads;
    }
}

$car1 = new Auto('Audi', 'A4', '2002');
echo $car1->showinfo();
