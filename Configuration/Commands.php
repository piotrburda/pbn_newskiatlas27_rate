<?php

return array(
    'pbnrate:cleanup' => array(
        'class' => \Pbn\PbnNewskiatlas27Rate\Command\CleanupCommand::class,
    ),
    'pbnrate:seed' => array(
        'class' => \Pbn\PbnNewskiatlas27Rate\Command\SeedQuestionsCommand::class,
    ),
);
