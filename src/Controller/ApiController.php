<?php

namespace App\Controller;

use App\Entity\Tag;
use App\Entity\User;
use App\Entity\Video;
use App\Service\EncryptionService;
use App\Service\VideoScraper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security as SecurityBundleSecurity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/')]
class ApiController extends AbstractController
{
    public function __construct(
        private readonly EncryptionService      $encryptionService,
        private readonly VideoScraper           $scraper,
        private readonly EntityManagerInterface $em
    ) {}

    private function getDecryptionPassword(Request $request): string
    {
        $password = $request->getSession()->get('decryption_password');
        if (!$password) {
            throw new \RuntimeException('Session not started', 403);
        }
        return $password;
    }

    private function getPayload(Request $request): array
    {
        return json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }

    private function syncTags(Video $video, array|string $tagsPayload, string $password): void
    {
        $tagRepo = $this->em->getRepository(Tag::class);
        $tagsRaw = is_array($tagsPayload) ? $tagsPayload : explode(',', $tagsPayload);

        // Si on met à jour, on commence par vider les anciens tags
        foreach ($video->getTags() as $oldTag) {
            $video->getTags()->removeElement($oldTag);
        }

        foreach ($tagsRaw as $tagName) {
            $tagName = trim($tagName);
            if (!$tagName) {
                continue;
            }

            $allUserTags = $tagRepo->findBy(['user' => $this->getUser()]);
            $tag = null;
            foreach ($allUserTags as $existingTag) {
                try {
                    $decryptedName = $this->encryptionService->decrypt($existingTag->getEncryptedName(), $password);
                    if ($decryptedName === $tagName) {
                        $tag = $existingTag;
                        break;
                    }
                } catch (\Exception) {}
            }

            if (!$tag) {
                $tag = new Tag();
                $tag->setEncryptedName($this->encryptionService->encrypt($tagName, $password));
                $tag->setUser($this->getUser());
                $this->em->persist($tag);
            }
            $video->addTag($tag);
        }
    }

    #[Route('/', name: 'app_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('base.html.twig');
    }

    #[Route('/api/csrf-token', methods: ['GET'])]
    public function getCsrfToken(CsrfTokenManagerInterface $tokenManager): JsonResponse
    {
        return new JsonResponse(['token' => $tokenManager->getToken('authenticate')->getValue()]);
    }

    #[Route('/api/register', methods: ['POST'])]
    public function register(Request $request, UserPasswordHasherInterface $hasher, CsrfTokenManagerInterface $tokenManager, SecurityBundleSecurity $security): JsonResponse
    {
        try {
            $data = $this->getPayload($request);
            $username = $data['username'] ?? null;
            $password = $data['password'] ?? null;
            $csrfToken = $data['_csrf_token'] ?? null;

            if (!$tokenManager->isTokenValid(new CsrfToken('authenticate', $csrfToken))) {
                return new JsonResponse(['error' => 'Invalid CSRF token'], 403);
            }

            if (!$username || !$password) {
                return new JsonResponse(['error' => 'Username and password required'], 400);
            }

            $existingUser = $this->em->getRepository(User::class)->findOneBy(['username' => $username]);
            if ($existingUser) {
                return new JsonResponse(['error' => 'Username already exists'], 400);
            }

            $user = new User();
            $user->setUsername($username);
            $user->setPassword($hasher->hashPassword($user, $password));

            $this->em->persist($user);
            $this->em->flush();

            // Login directly
            $security->login($user);

            return new JsonResponse(['status' => 'User created and logged in'], 201);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/api/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the form_login key on your firewall.');
    }

    #[Route('/api/logout', name: 'app_logout', methods: ['GET'])]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route('/api/me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['authenticated' => false]);
        }

        return new JsonResponse([
            'authenticated' => true,
            'username' => $user->getUserIdentifier(),
            'sessionStarted' => (bool)$this->container->get('request_stack')->getSession()->get('decryption_password')
        ]);
    }

    #[Route('/api/session', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function startSession(Request $request): JsonResponse
    {
        try {
            $data = $this->getPayload($request);
            $password = $data['password'] ?? null;

            if (!$password || strlen($password) < 8) {
                return new JsonResponse(['error' => 'Password too short (min 8 chars)'], 400);
            }

            $request->getSession()->set('decryption_password', $password);

            return new JsonResponse(['status' => 'Session started']);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/api/videos/preview', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function previewVideo(Request $request): JsonResponse
    {
        try {
            $payload = $this->getPayload($request);
            $url = $payload['url'] ?? null;

            if (!$url) {
                return new JsonResponse(['error' => 'URL required'], 400);
            }

            $scrapedData = $this->scraper->scrape($url);
            return new JsonResponse($scrapedData);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/api/videos', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function listVideos(Request $request): JsonResponse
    {
        try {
            $password = $this->getDecryptionPassword($request);

            $videos = $this->em->getRepository(Video::class)->findBy(['user' => $this->getUser()]);
            $data = [];

            foreach ($videos as $video) {
                $tags = [];
                foreach ($video->getTags() as $tag) {
                    $tags[] = [
                        'id' => $tag->getId(),
                        'name' => $this->encryptionService->decrypt($tag->getEncryptedName(), $password),
                    ];
                }

                $data[] = [
                    'id' => $video->getId(),
                    'url' => $this->encryptionService->decrypt($video->getEncryptedUrl(), $password),
                    'title' => $this->encryptionService->decrypt($video->getEncryptedTitle(), $password),
                    'description' => $this->encryptionService->decrypt($video->getEncryptedDescription(), $password),
                    'image' => $this->encryptionService->decrypt($video->getEncryptedImage(), $password),
                    'tags' => $tags,
                ];
            }

            return new JsonResponse($data);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], $e->getCode());
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/api/videos', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function addVideo(Request $request): JsonResponse
    {
        try {
            $password = $this->getDecryptionPassword($request);
            $payload = $this->getPayload($request);
            $url = $payload['url'] ?? null;

            if (!$url) {
                return new JsonResponse(['error' => 'URL required'], 400);
            }

            $video = new Video();
            $video->setUser($this->getUser());
            $video->setEncryptedUrl($this->encryptionService->encrypt($url, $password));
            $video->setEncryptedTitle($this->encryptionService->encrypt($payload['title'] ?? 'Sans titre', $password));

            if (!empty($payload['description'])) {
                $video->setEncryptedDescription($this->encryptionService->encrypt($payload['description'], $password));
            }
            if (!empty($payload['image'])) {
                $video->setEncryptedImage($this->encryptionService->encrypt($payload['image'], $password));
            }

            if (!empty($payload['tags'])) {
                $this->syncTags($video, $payload['tags'], $password);
            }

            $this->em->persist($video);
            $this->em->flush();

            return new JsonResponse(['status' => 'Video added', 'id' => $video->getId()]);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], $e->getCode());
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/api/videos/{id}', methods: ['PUT'])]
    #[IsGranted('ROLE_USER')]
    public function updateVideo(int $id, Request $request): JsonResponse
    {
        try {
            $password = $this->getDecryptionPassword($request);
            $video = $this->em->getRepository(Video::class)->findOneBy(['id' => $id, 'user' => $this->getUser()]);
            if (!$video) {
                return new JsonResponse(['error' => 'Video not found'], 404);
            }

            $payload = $this->getPayload($request);

            if (isset($payload['url'])) {
                $video->setEncryptedUrl($this->encryptionService->encrypt($payload['url'], $password));
            }
            if (isset($payload['title'])) {
                $video->setEncryptedTitle($this->encryptionService->encrypt($payload['title'], $password));
            }
            if (isset($payload['description'])) {
                $video->setEncryptedDescription($this->encryptionService->encrypt($payload['description'], $password));
            }
            if (isset($payload['image'])) {
                $video->setEncryptedImage($this->encryptionService->encrypt($payload['image'], $password));
            }

            if (isset($payload['tags'])) {
                $this->syncTags($video, $payload['tags'], $password);
            }

            $this->em->flush();
            return new JsonResponse(['status' => 'Video updated']);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], $e->getCode());
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/api/videos/{id}', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function deleteVideo(int $id, Request $request): JsonResponse
    {
        try {
            $this->getDecryptionPassword($request);

            $video = $this->em->getRepository(Video::class)->findOneBy(['id' => $id, 'user' => $this->getUser()]);
            if (!$video) {
                return new JsonResponse(['error' => 'Video not found'], 404);
            }

            $this->em->remove($video);
            $this->em->flush();

            return new JsonResponse(['status' => 'Video deleted']);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], $e->getCode());
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }
}
