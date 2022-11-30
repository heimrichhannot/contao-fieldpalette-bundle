<?php

/*
 * Copyright (c) 2022 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

use HeimrichHannot\FieldpaletteBundle\Dca\DcaGenerator;

$GLOBALS['TL_DCA']['tl_fieldpalette'] = DcaGenerator::generateFieldpaletteBaseDca();
