<?php

namespace Vayu\Http;

/**
 * Wraps a single entry from $_FILES and provides safe storage.
 */
class UploadedFile
{
    public function __construct(
        protected string $originalName,
        protected string $mimeType,
        protected string $tmpPath,
        protected int $error,
        protected int $size,
    ) {
    }

    /**
     * @param array{name: string, type: string, tmp_name: string, error: int, size: int} $file
     */
    public static function fromArray(array $file): self
    {
        return new self(
            (string) ($file['name'] ?? ''),
            (string) ($file['type'] ?? ''),
            (string) ($file['tmp_name'] ?? ''),
            (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE),
            (int) ($file['size'] ?? 0),
        );
    }

    public function getClientOriginalName(): string
    {
        return $this->originalName;
    }

    public function getClientExtension(): string
    {
        return strtolower(pathinfo($this->originalName, PATHINFO_EXTENSION));
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getError(): int
    {
        return $this->error;
    }

    public function getPathname(): string
    {
        return $this->tmpPath;
    }

    public function isValid(): bool
    {
        return $this->error === UPLOAD_ERR_OK && $this->tmpPath !== '';
    }

    /**
     * Generate a random, safe file name preserving the extension.
     */
    public function hashName(): string
    {
        $extension = $this->getClientExtension();

        return bin2hex(random_bytes(16)) . ($extension !== '' ? '.' . $extension : '');
    }

    /**
     * Move the file into storage/<directory>. Returns the stored path relative
     * to the storage directory, or null on failure.
     */
    public function store(string $directory = 'uploads', ?string $name = null): ?string
    {
        return $this->storeAs($directory, $name ?? $this->hashName());
    }

    public function storeAs(string $directory, string $name): ?string
    {
        if (!$this->isValid()) {
            return null;
        }

        $directory = trim($directory, '/');
        $targetDir = \app()->storagePath($directory);
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0775, true);
        }

        $name = basename($name); // prevent path traversal
        $target = $targetDir . '/' . $name;

        $moved = is_uploaded_file($this->tmpPath)
            ? move_uploaded_file($this->tmpPath, $target)
            : @rename($this->tmpPath, $target);

        return $moved ? $directory . '/' . $name : null;
    }
}
