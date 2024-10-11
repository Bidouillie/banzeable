<?php

namespace App\Entity;

use App\Enum\CourseAspect;
use App\Repository\CourseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CourseRepository::class)]
class Course
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Assert\Length(min: 3)]
    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(enumType: CourseAspect::class)]
    private ?CourseAspect $aspect = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $rating = null;

    #[ORM\Column(nullable: true)]
    private ?float $price = null;

    /**
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class, mappedBy: 'courses')]
    private Collection $owners;

    /**
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class, mappedBy: 'studies')]
    private Collection $students;

    /**
     * @var Collection<int, Variation>
     */
    #[ORM\OneToMany(targetEntity: Variation::class, mappedBy: 'course', orphanRemoval: true)]
    private Collection $variations;

    public function __construct()
    {
        $this->owners = new ArrayCollection();
        $this->variations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getAspect(): ?CourseAspect
    {
        return $this->aspect;
    }

    public function setAspect(CourseAspect $aspect): static
    {
        $this->aspect = $aspect;

        return $this;
    }

    public function getRating(): ?int
    {
        return $this->rating;
    }

    public function setRating(?int $rating): static
    {
        $this->rating = $rating;

        return $this;
    }

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(?float $price): static
    {
        $this->price = $price;

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getOwners(): Collection
    {
        return $this->owners;
    }

    public function addOwner(User $user): static
    {
        if (!$this->owners->contains($user)) {
            $this->owners->add($user);
            $user->addCourse($this);
        }

        return $this;
    }

    public function removeOwner(User $user): static
    {
        if ($this->owners->removeElement($user)) {
            $user->removeCourse($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getStudents(): Collection
    {
        return $this->students;
    }

    public function addStudent(User $user): static
    {
        if (!$this->students->contains($user)) {
            $this->students->add($user);
            $user->addStudy($this);
        }

        return $this;
    }

    public function removeStudent(User $user): static
    {
        if ($this->students->removeElement($user)) {
            $user->removeStudy($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, Variation>
     */
    public function getVariations(): Collection
    {
        return $this->variations;
    }

    public function addVariation(Variation $variation): static
    {
        if (!$this->variations->contains($variation)) {
            $this->variations->add($variation);
            $variation->setCourse($this);
        }

        return $this;
    }

    public function removeVariation(Variation $variation): static
    {
        if ($this->variations->removeElement($variation)) {
            // set the owning side to null (unless already changed)
            if ($variation->getCourse() === $this) {
                $variation->setCourse(null);
            }
        }

        return $this;
    }
}
