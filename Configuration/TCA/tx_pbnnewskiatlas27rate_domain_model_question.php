<?php

defined('TYPO3_MODE') or die();

$ll = 'LLL:EXT:pbn_newskiatlas27_rate/Resources/Private/Language/locallang_db.xlf:';

return array(
    'ctrl' => array(
        'title' => $ll . 'tx_pbnnewskiatlas27rate_domain_model_question',
        'label' => 'title',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'cruser_id' => 'cruser_id',
        'sortby' => 'sorting',
        'delete' => 'deleted',
        'enablecolumns' => array(
            'disabled' => 'hidden',
        ),
        'hideTable' => true,
        'searchFields' => 'title,description',
        'iconfile' => 'EXT:pbn_newskiatlas27_rate/Resources/Public/Icons/question.svg',
    ),
    'interface' => array(
        'showRecordFieldList' => 'hidden,title,description,required,is_summary,applies_to',
    ),
    'types' => array(
        '1' => array(
            'showitem' => 'title, description, --palette--;;flags, applies_to, --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access, hidden',
        ),
    ),
    'palettes' => array(
        'flags' => array(
            'showitem' => 'required, is_summary',
        ),
    ),
    'columns' => array(
        'hidden' => array(
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.visible',
            'config' => array(
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'items' => array(
                    array(0 => '', 1 => '', 'invertStateDisplay' => true),
                ),
            ),
        ),
        'questiongroup' => array(
            'config' => array(
                'type' => 'passthrough',
            ),
        ),
        'title' => array(
            'label' => $ll . 'question.title',
            'config' => array(
                'type' => 'input',
                'size' => 50,
                'max' => 255,
                'eval' => 'trim,required',
            ),
        ),
        'description' => array(
            'label' => $ll . 'question.description',
            'description' => $ll . 'question.description.help',
            'config' => array(
                'type' => 'text',
                'cols' => 50,
                'rows' => 2,
                'eval' => 'trim',
            ),
        ),
        'required' => array(
            'label' => $ll . 'question.required',
            'config' => array(
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'items' => array(
                    array(0 => '', 1 => ''),
                ),
                'default' => 0,
            ),
        ),
        'is_summary' => array(
            'label' => $ll . 'question.is_summary',
            'description' => $ll . 'question.is_summary.help',
            'config' => array(
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'items' => array(
                    array(0 => '', 1 => ''),
                ),
                'default' => 0,
            ),
        ),
        'applies_to' => array(
            'label' => $ll . 'question.applies_to',
            'description' => $ll . 'question.applies_to.help',
            'config' => array(
                'type' => 'input',
                'size' => 50,
                'max' => 1024,
                'eval' => 'trim,nospace',
                'placeholder' => 'tx_pbnnewskiatlas27_domain_model_resort',
            ),
        ),
    ),
);
