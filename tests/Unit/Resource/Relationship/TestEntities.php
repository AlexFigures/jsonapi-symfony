<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Unit\Resource\Relationship;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class TestAuthor
{
    public string $id = '';
    public string $name = '';

    /** @var Collection<int, TestBook> */
    public Collection $books;

    public function __construct()
    {
        $this->books = new ArrayCollection();
    }

    public function addBook(TestBook $book): void
    {
        if (!$this->books->contains($book)) {
            $this->books->add($book);
            $book->setAuthor($this);
        }
    }

    public function removeBook(TestBook $book): void
    {
        if ($this->books->contains($book)) {
            $this->books->removeElement($book);
            $book->setAuthor(null);
        }
    }

    public function getBooks(): Collection
    {
        return $this->books;
    }

    public function setBooks(Collection $books): void
    {
        $this->books = $books;
    }
}

class TestBook
{
    public string $id = '';
    public string $title = '';
    public ?TestAuthor $author = null;

    public function setAuthor(?TestAuthor $author): void
    {
        $this->author = $author;
    }

    public function getAuthor(): ?TestAuthor
    {
        return $this->author;
    }
}
