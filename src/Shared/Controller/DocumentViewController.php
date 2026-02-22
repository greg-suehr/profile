<?php

namespace App\Shared\Controller;

use App\Shared\Entity\DocumentView;
use App\Shared\Entity\ResearchDocument;
use App\Shared\Repository\ResearchDocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

#[Route(condition: "request.getHost() matches '%gregsuehr_match%'")]
final class DocumentViewController extends AbstractController
{
  /**
   * Serve a research document PDF inline (browser viewer) and log the view.
   * 
   * The PDF is served with Content-Disposition: inline so the browser renders
   * it using its native PDF viewer without triggering a download dialog.
   */
  #[Route('/research/documents/{id}/view', name: 'profile_document_view', methods: ['GET'])]
  public function view(
    int $id,
    Request $request,
    ResearchDocumentRepository $docRepo,
    EntityManagerInterface $em,
  ): Response
  {
    $document = $docRepo->find($id);

    if (!$document || !$document->isPublished() || !$document->getFilePath()) {
      throw $this->createNotFoundException('Document not found.');
    }

    $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/documents';
    $filePath  = $uploadDir . '/' . $document->getFilePath();

    if (!file_exists($filePath)) {
      throw $this->createNotFoundException('File not found on disk.');
    }

    $view = new DocumentView();
    $view->setDocument($document);
    $view->setViewedAt(new \DateTimeImmutable());
    $view->setIpAddress($request->getClientIp());
    $view->setUserAgent(mb_substr((string) $request->headers->get('User-Agent', ''), 0, 500));
    $view->setReferer(mb_substr((string) $request->headers->get('Referer', ''), 0, 500));

    $em->persist($view);
    $em->flush();

    $response = new BinaryFileResponse($filePath);
    $response->headers->set('Content-Type', 'application/pdf');
    $response->setContentDisposition(
      ResponseHeaderBag::DISPOSITION_INLINE,
      $document->getOriginalFileName() ?? $document->getFilePath()
    );

    $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

    return $response;
  }

  /**
   * Force-download a research document PDF.
   */
  #[Route('/research/documents/{id}/download', name: 'profile_document_download', methods: ['GET'])]
  public function download(
    int $id,
    Request $request,
    ResearchDocumentRepository $docRepo,
    EntityManagerInterface $em,
  ): Response
  {
    $document = $docRepo->find($id);

    if (!$document || !$document->isPublished() || !$document->getFilePath()) {
      throw $this->createNotFoundException('Document not found.');
    }

    $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/documents';
    $filePath  = $uploadDir . '/' . $document->getFilePath();

    if (!file_exists($filePath)) {
      throw $this->createNotFoundException('File not found on disk.');
    }

    $view = new DocumentView();
    $view->setDocument($document);
    $view->setViewedAt(new \DateTimeImmutable());
    $view->setIpAddress($request->getClientIp());
    $view->setUserAgent(mb_substr((string) $request->headers->get('User-Agent', ''), 0, 500));
    $view->setReferer(mb_substr((string) $request->headers->get('Referer', ''), 0, 500));

    $em->persist($view);
    $em->flush();

    $response = new BinaryFileResponse($filePath);
    $response->setContentDisposition(
      ResponseHeaderBag::DISPOSITION_ATTACHMENT,
      $document->getOriginalFileName() ?? $document->getFilePath()
    );

    return $response;
  }
}
