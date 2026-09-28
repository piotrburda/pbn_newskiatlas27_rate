<?php

defined('TYPO3_MODE') or die();

$ll = 'LLL:EXT:pbn_newskiatlas27_rate/Resources/Private/Language/locallang_db.xlf:';

return array(
    'ctrl' => array(
        'title' => $ll . 'tx_pbnnewskiatlas27rate_domain_model_vote',
        'label' => 'foreign_uid',
        'label_alt' => 'foreign_table,crdate',
        'label_alt_force' => true,
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'default_sortby' => 'crdate DESC',
        'delete' => 'deleted',
        'enablecolumns' => array(
            'disabled' => 'hidden',
        ),
        'searchFields' => 'foreign_table,foreign_uid',
        'iconfile' => 'EXT:pbn_newskiatlas27_rate/Resources/Public/Icons/vote.svg',
    ),
    'interface' => array(
        'showRecordFieldList' => 'hidden,crdate,foreign_table,foreign_uid,fe_user,answers',
    ),
    'types' => array(
        '1' => array(
            'showitem' => 'hidden, crdate, foreign_table, foreign_uid, fe_user, answers',
        ),
    ),
    'columns' => array(
        'hidden' => array(
            'label' => $ll . 'vote.hidden',
            'description' => $ll . 'vote.hidden.help',
            'config' => array(
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'items' => array(
                    array(0 => '', 1 => ''),
                ),
            ),
        ),
        'crdate' => array(
            'label' => $ll . 'vote.crdate',
            'config' => array(
                'type' => 'input',
                'renderType' => 'inputDateTime',
                'eval' => 'datetime,int',
                'readOnly' => true,
            ),
        ),
        'foreign_table' => array(
            'label' => $ll . 'vote.foreign_table',
            'config' => array(
                'type' => 'input',
                'size' => 40,
                'readOnly' => true,
            ),
        ),
        'foreign_uid' => array(
            'label' => $ll . 'vote.foreign_uid',
            'config' => array(
                'type' => 'input',
                'size' => 10,
                'eval' => 'int',
                'readOnly' => true,
            ),
        ),
        'fe_user' => array(
            'label' => $ll . 'vote.fe_user',
            'config' => array(
                'type' => 'group',
                'internal_type' => 'db',
                'allowed' => 'fe_users',
                'size' => 1,
                'maxitems' => 1,
                'readOnly' => true,
            ),
        ),
        'ip_hash' => array(
            'config' => array(
                'type' => 'passthrough',
            ),
        ),
        'answers' => array(
            'label' => $ll . 'vote.answers',
            'config' => array(
                'type' => 'inline',
                'foreign_table' => 'tx_pbnnewskiatlas27rate_domain_model_voteanswer',
                'foreign_field' => 'vote',
                'readOnly' => true,
                'appearance' => array(
                    'collapseAll' => true,
                    'enabledControls' => array(
                        'info' => true,
                        'new' => false,
                        'dragdrop' => false,
                        'sort' => false,
                        'hide' => false,
                        'delete' => false,
                        'localize' => false,
                    ),
                ),
            ),
        ),
    ),
);
