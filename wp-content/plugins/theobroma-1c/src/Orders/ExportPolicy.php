<?php
declare(strict_types=1); namespace Theobroma\OneC\Orders;
final class ExportPolicy { public function shouldQueue(bool $paid,ExportState $state):bool{return $paid||$state->exportedBefore();} }
