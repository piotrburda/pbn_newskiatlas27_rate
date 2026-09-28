<?php

defined('TYPO3_MODE') or die();

$ll = 'LLL:EXT:pbn_newskiatlas27_rate/Resources/Private/Language/locallang_db.xlf:';

return array(
    'ctrl' => array(
        'title' => $ll . 'tx_pbnnewskiatlas27rate_domain_model_voteanswer',
        'label' => 'question',
        'label_alt' => 'value',
        'label_alt_force' => true,
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'delete' => 'deleted',
        'hideTable' => true,
        'iconfile' => 'EXT:pbn_newskiatlas27_rate/Resources/Public/Icons/voteanswer.svg',
    ),
    'interface' => array(
        'showRecordFieldList' => 'question,value',
    ),
    'types' => array(
        '1' => array(
            'showitem' => 'question, value',
        ),
    ),
    'columns' => array(
        'vote' => array(
            'config' => array(
                'type' => 'passthrough',
            ),
        ),
        'question' => array(
            'label' => $ll . 'voteanswer.question',
            'config' => array(
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'tx_pbnnewskiatlas27rate_domain_model_question',
                'items' => array(
                    array('', 0),
                ),
                'readOnly' => true,
            ),
        ),
        'value' => array(
            'label' => $ll . 'voteanswer.value',
            'config' => array(
                'type' => 'input',
                'size' => 5,
                'eval' => 'int',
                'readOnly' => true,
            ),
        ),
    ),
);
