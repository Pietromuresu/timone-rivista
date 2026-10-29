<?php

namespace App\Support\Timeline;

use RuntimeException;

/** Annulla/ripeti: conflitto o spazio insufficiente, fa annullare la transazione (TimelineActionService). */
final class TimelineConflict extends RuntimeException {}
