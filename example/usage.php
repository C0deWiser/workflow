<?php

$article = new Article();

$transitions = $article->workflow()->getRelevantTransitions();
foreach ($transitions as $transition) {
    $target = $transition->getTarget();
    $problem = $transition->hasProblem();
}

try {
    $article->fill($request->all());
    $article->workflow()->setTransitionComment('comment');
} catch (\Codewiser\Workflow\Exceptions\WorkflowException $exception) {
    echo $exception->getMessage();
}

/** @var \Codewiser\Journalism\Journal[] $history */
$history = $article->workflow()->journal()->limit(15)->get();
foreach ($history as $item) {
    $item->created_at;
    $item->event;
    $item->user;
    $item->payload;
}

