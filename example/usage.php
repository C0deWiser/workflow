<?php

$article = new Article();

$transitions = $article->workflow()->getRelevantTransitions();
foreach ($transitions as $transition) {
    $target = $transition->getTarget();
    $problem = $transition->hasProblem();
}

$article->techWorkflow()->transit($article->workflow()->getRelevantTransitions()->first()->getTarget());

