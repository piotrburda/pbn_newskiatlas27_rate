<?php

$EM_CONF[$_EXTKEY] = array(
    'title' => 'New ski atlas - rating',
    'description' => 'Star ratings (1-5) with backend-defined questions, one vote per record and visitor within a TypoScript-defined lock period.',
    'category' => 'fe',
    'author' => 'Pbn',
    'author_email' => '',
    'author_company' => '',
    'state' => 'beta',
    'clearCacheOnLoad' => 1,
    'version' => '0.1.0',
    'constraints' => array(
        'depends' => array(
            'typo3' => '9.5.0-9.5.99',
            'php' => '7.4.0-7.4.99',
        ),
        'conflicts' => array(),
        'suggests' => array(
            'pbn_newskiatlas27' => '',
        ),
    ),
);
