<?php
declare(strict_types=1); namespace Theobroma\OneC\Orders;
final readonly class ExportState {
 public function __construct(public int $revision=0,public int $acknowledgedRevision=0,public string $lastExportedAt=''){}
 public function queue():self{return new self($this->revision+1,$this->acknowledgedRevision,$this->lastExportedAt);}
 public function acknowledge(int $revision,string $at=''):self{return $revision>$this->acknowledgedRevision?new self(max($this->revision,$revision),$revision,$at):$this;}
 public function pending():bool{return $this->revision>$this->acknowledgedRevision;}
 public function exportedBefore():bool{return $this->acknowledgedRevision>0;}
}
