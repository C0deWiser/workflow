<?php

$article = new Article();

$transitions = $article->workflow()->getRelevantTransitions();
foreach ($transitions as $transition) {
    $target = $transition->getTarget();
    $problem = $transition->hasProblem();
}

try {
    $article->journalMemo('comment');
    $article->fill($request->all());
} catch (\Codewiser\Workflow\Exceptions\WorkflowException $exception) {
    echo $exception->getMessage();
}

/** @var \Codewiser\Journalism\Journal[] $history */
$history = $article->journal()->limit(15)->get();
foreach ($history as $item) {
    $item->created_at;
    $item->event;
    $item->user;
    $item->payload;
}

