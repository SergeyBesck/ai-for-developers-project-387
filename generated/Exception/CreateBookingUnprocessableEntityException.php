<?php

namespace App\Generated\Exception;

class CreateBookingUnprocessableEntityException extends UnprocessableEntityException
{
    public function __construct(
        /**
         * @var \App\Generated\Model\Problem
         */
        private readonly \App\Generated\Model\Problem $problem,
        /**
         * @var \Symfony\Contracts\HttpClient\ResponseInterface
         */
        private readonly \Symfony\Contracts\HttpClient\ResponseInterface $response
    )
    {
        parent::__construct('Client error');
    }
    public function getProblem(): \App\Generated\Model\Problem
    {
        return $this->problem;
    }
    public function getResponse(): \Symfony\Contracts\HttpClient\ResponseInterface
    {
        return $this->response;
    }
}