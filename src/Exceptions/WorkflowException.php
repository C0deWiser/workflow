<?php


namespace Codewiser\Workflow\Exceptions;


class WorkflowException extends \Exception
{
    /**
     * The status code to use for the response.
     *
     * @var int
     */
    public int $status = 500;

    public function jsonSerialize()
    {
        $json = [
            'message' => $this->getMessage()
        ];

        return $json;
    }

    public function toJson($options = 0)
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * Render the exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function render($request)
    {
        if ($request->expectsJson()) {
            return response()
                ->json($this->jsonSerialize(), $this->status);
        } else {
            return response($this->getMessage(), $this->status);
        }
    }
}