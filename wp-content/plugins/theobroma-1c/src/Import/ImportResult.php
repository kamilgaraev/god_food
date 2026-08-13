<?php
declare(strict_types=1); namespace Theobroma\OneC\Import;
final readonly class ImportResult {public function __construct(public int$applied=0,public int$skipped=0,public int$ambiguous=0,public int$errors=0){}public function context():array{return['applied'=>$this->applied,'skipped'=>$this->skipped,'ambiguous'=>$this->ambiguous,'errors'=>$this->errors];}}
