<?php

defined('TYPO3_MODE') or die();

// Rating parameters are only ever sent via POST to the JSON page type, but keep
// them out of cHash calculation in case somebody links them via GET.
$GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'][] = 'pbnrate';
