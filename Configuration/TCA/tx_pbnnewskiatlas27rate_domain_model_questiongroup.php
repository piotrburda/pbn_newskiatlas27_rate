<?php

defined('TYPO3_MODE') or die();

$ll = 'LLL:EXT:pbn_newskiatlas27_rate/Resources/Private/Language/locallang_db.xlf:';

return array(
    'ctrl' => array(
        'title' => $ll . 'tx_pbnnewskiatlas27rate_domain_model_questiongroup',
        'label' => 'title',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'cruser_id' => 'cruser_id',
        'sortby' => 'sorting',
        'delete' => 'deleted',
        'enablecolumns' => array(
            'disabled' => 'hidden',
        ),
        'searchFields' => 'title,description',
        'iconfile' => 'EXT:pbn_newskiatlas27_rate/Resources/Public/Icons/questiongroup.svg',
    ),
    'interface' => array(
        'showRecordFieldList' => 'hidden,title,description,questions',
    ),
    'types' => array(
        '1' => array(
            'showitem' => 'title, description, questions, --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access, hidden',
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
        'title' => array(
            'label' => $ll . 'questiongroup.title',
            'config' => array(
                'type' => 'input',
                'size' => 50,
                'max' => 255,
                'eval' => 'trim,required',
            ),
        ),
        'description' => array(
            'label' => $ll . 'questiongroup.description',
            'config' => array(
                'type' => 'text',
                'cols' => 50,
                'rows' => 3,
                'eval' => 'trim',
            ),
        ),
        'questions' => array(
            'label' => $ll . 'questiongroup.questions',
            'config' => array(
                'type' => 'inline',
                'foreign_table' => 'tx_pbnnewskiatlas27rate_domain_model_question',
                'foreign_field' => 'questiongroup',
                'foreign_sortby' => 'sorting',
                'maxitems' => 99,
                'appearance' => array(
                    'collapseAll' => true,
                    'expandSingle' => true,
                    'useSortable' => true,
                    'showPossibleLocalizationRecords' => false,
                    'showAllLocalizationLink' => false,
                    'enabledControls' => array(
                        'info' => true,
                        'new' => true,
                        'dragdrop' => true,
                        'sort' => true,
                        'hide' => true,
                        'delete' => true,
                        'localize' => false,
                    ),
                ),
            ),
        ),
    ),
);
