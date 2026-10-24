<?php

namespace App\Services\Sources;

/** Een bron vroeg om een pauze (429/503/403); tot die voorbij is sturen we niets. */
class SourceCoolingDown extends \RuntimeException {}
