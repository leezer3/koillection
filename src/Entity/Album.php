<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Attribute\Upload;
use App\Entity\Interfaces\BreadcrumbableInterface;
use App\Entity\Interfaces\CacheableInterface;
use App\Entity\Interfaces\LoggableInterface;
use App\Enum\VisibilityEnum;
use App\Repository\AlbumRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection as DoctrineCollection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AlbumRepository::class)]
#[ORM\Table(name: 'koi_album')]
#[ORM\Index(name: 'idx_album_final_visibility', columns: ['final_visibility'])]
#[ApiResource(
    denormalizationContext: ['groups' => ['album:write']],
    normalizationContext: ['groups' => ['album:read']],
    operations: [
        new Get(),
        new Put(),
        new Delete(),
        new Patch(),
        new GetCollection(),
        new Post(),
        new Post(uriTemplate: '/albums/{id}/image', denormalizationContext: ['groups' => ['album:image']], inputFormats: ['multipart' => ['multipart/form-data']], openapiContext: ['summary' => 'Upload the Album image.']),
    ]
)]
#[ApiResource(uriTemplate: '/albums/{id}/children', uriVariables: ['id' => new Link(fromClass: Album::class, fromProperty: 'children')], normalizationContext: ['groups' => ['album:read']], operations: [new GetCollection()])]
#[ApiResource(uriTemplate: '/albums/{id}/parent', uriVariables: ['id' => new Link(fromClass: Album::class, fromProperty: 'parent')], normalizationContext: ['groups' => ['album:read']], operations: [new Get()])]
#[ApiResource(uriTemplate: '/photos/{id}/album', uriVariables: ['id' => new Link(fromClass: Photo::class, fromProperty: 'album')], normalizationContext: ['groups' => ['album:read']], operations: [new Get()])]
class Album implements BreadcrumbableInterface, LoggableInterface, CacheableInterface, \Stringable
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 36, unique: true, options: ['fixed' => true])]
    #[Groups(['album:read'])]
    private string $id;

    #[ORM\Column(type: Types::STRING)]
    #[Assert\NotBlank]
    #[Groups(['album:read', 'album:write'])]
    private ?string $title = null;

    #[ORM\Column(type: Types::STRING, length: 6)]
    #[Groups(['album:read'])]
    private ?string $color = null;

    #[Upload(pathProperty: 'image', deleteProperty: 'deleteImage', maxWidth: 200, maxHeight: 200)]
    #[Assert\Image(mimeTypes: ['image/png', 'image/jpeg', 'image/webp', 'image/avif'], groups: ['album:image'])]
    #[Groups(['album:write', 'album:image'])]
    private ?File $file = null;

    #[ORM\Column(type: Types::STRING, nullable: true, unique: true)]
    #[Groups(['album:read'])]
    private ?string $image = null;

    #[Groups(['album:write'])]
    private ?bool $deleteImage = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'albums')]
    #[Groups(['album:read'])]
    private ?User $owner = null;

    #[ORM\OneToMany(targetEntity: Photo::class, mappedBy: 'album', cascade: ['all'])]
    private DoctrineCollection $photos;

    #[ApiProperty(readableLink: false, writableLink: false)]
    #[ORM\OneToMany(targetEntity: Album::class, mappedBy: 'parent', cascade: ['all'])]
    #[ORM\OrderBy(['title' => Criteria::ASC])]
    private DoctrineCollection $children;

    #[ApiProperty(readableLink: false, writableLink: false)]
    #[ORM\ManyToOne(targetEntity: Album::class, inversedBy: 'children')]
    #[Groups(['album:read', 'album:write'])]
    #[Assert\Expression('not (value == this)', message: 'error.parent.same_as_current_object')]
    private ?Album $parent = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['album:read'])]
    private int $seenCounter = 0;

    #[ORM\Column(type: Types::JSON)]
    #[Groups(['album:read'])]
    private ?array $cachedValues = [];

    #[ApiProperty(readableLink: false, writableLink: false)]
    #[ORM\OneToOne(targetEntity: DisplayConfiguration::class, cascade: ['all'])]
    private DisplayConfiguration $childrenDisplayConfiguration;

    #[ApiProperty(readableLink: false, writableLink: false)]
    #[ORM\OneToOne(targetEntity: DisplayConfiguration::class, cascade: ['all'])]
    private DisplayConfiguration $photosDisplayConfiguration;

    #[ORM\Column(type: Types::STRING, length: 10)]
    #[Groups(['album:read', 'album:write'])]
    #[Assert\Choice(choices: VisibilityEnum::VISIBILITIES)]
    private string $visibility = VisibilityEnum::VISIBILITY_PUBLIC;

    #[ORM\Column(type: Types::STRING, length: 10, nullable: true)]
    #[Groups(['album:read'])]
    private ?string $parentVisibility = null;

    #[ORM\Column(type: Types::STRING, length: 10)]
    #[Groups(['album:read'])]
    private string $finalVisibility;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['album:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['album:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->id = Uuid::v4()->toRfc4122();
        $this->photos = new ArrayCollection();
        $this->children = new ArrayCollection();
        $this->childrenDisplayConfiguration = new DisplayConfiguration();
        $this->photosDisplayConfiguration = new DisplayConfiguration();
    }

    public function __toString(): string
    {
        return $this->getTitle() ?? '';
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(string $color): self
    {
        $this->color = $color;

        return $this;
    }

    public function getSeenCounter(): ?int
    {
        return $this->seenCounter;
    }

    public function setSeenCounter(int $seenCounter): self
    {
        $this->seenCounter = $seenCounter;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): self
    {
        $this->owner = $owner;

        return $this;
    }

    public function getPhotos(): DoctrineCollection
    {
        return $this->photos;
    }

    public function getChildren(): DoctrineCollection
    {
        return $this->children;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): self
    {
        $this->parent = $parent;

        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): self
    {
        $this->image = $image;

        return $this;
    }

    public function getFile(): ?File
    {
        return $this->file;
    }

    public function setFile(?File $file): self
    {
        $this->file = $file;
        // Force Doctrine to trigger an update
        if ($file instanceof UploadedFile) {
            $this->setUpdatedAt(new \DateTimeImmutable());
        }

        return $this;
    }

    public function getVisibility(): ?string
    {
        return $this->visibility;
    }

    public function setVisibility(string $visibility): self
    {
        $this->visibility = $visibility;

        return $this;
    }

    public function getParentVisibility(): ?string
    {
        return $this->parentVisibility;
    }

    public function setParentVisibility(?string $parentVisibility): self
    {
        $this->parentVisibility = $parentVisibility;

        return $this;
    }

    public function getFinalVisibility(): string
    {
        return $this->finalVisibility;
    }

    public function setFinalVisibility(string $finalVisibility): self
    {
        $this->finalVisibility = $finalVisibility;

        return $this;
    }

    public function getPhotosDisplayConfiguration(): DisplayConfiguration
    {
        return $this->photosDisplayConfiguration;
    }

    public function getChildrenDisplayConfiguration(): DisplayConfiguration
    {
        return $this->childrenDisplayConfiguration;
    }

    public function getCachedValues(): array
    {
        return $this->cachedValues;
    }

    public function setCachedValues(array $cachedValues): Album
    {
        $this->cachedValues = $cachedValues;

        return $this;
    }

    public function getDeleteImage(): ?bool
    {
        return $this->deleteImage;
    }

    public function setDeleteImage(?bool $deleteImage): Album
    {
        $this->deleteImage = $deleteImage;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }
}
