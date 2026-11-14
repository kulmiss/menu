<?php

namespace App\Support;

use RuntimeException;

/** The upload has too many pixels to decode safely (SECURITY_REPORT.md M6). */
class ImageTooLarge extends RuntimeException {}
