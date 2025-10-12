<?php

namespace App\Katzen\Service\Response;

final class ServiceResponse
{
  private function __construct(
    public readonly bool $success,
    public readonly mixed $data = null,
    public readonly ?string $message = null,
    public readonly array $errors = [],
    public readonly array $metadata = [],
  ) {}
  
  public static function success(
    mixed $data = null,
    ?string $message = null,
    array $metadata = []
  ): self {
    return new self(
      success: true,
      data: $data,
      message: $message,
      metadata: $metadata
    );
  }
  
  public static function failure(
    string|array $errors,
    ?string $message = null,
    mixed $data = null,
    array $metadata = []
  ): self {
    return new self(
      success: false,
      data: $data,
      message: $message,
      errors: is_string($errors) ? [$errors] : $errors,
      metadata: $metadata
    );
    }
  
  public function isSuccess(): bool
  {
    return $this->success;
  }
  
  public function isFailure(): bool
  {
    return !$this->success;
  }

  public function getData(): mixed
  {
    return $this->data;
  }

  public function getErrors(): array
  {
    return $this->errors;
  }

  public function getFirstError(): ?string
  {
    return $this->errors[0] ?? null;
  }
}
