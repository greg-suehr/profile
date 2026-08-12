<?php
  
namespace App\Shared\Controller\Samson;

use App\Service\SiteContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[Route(condition: "request.getHost() matches '%samson_match%'")]
class SamsonController extends AbstractController
{  
  public function __construct(    
  )
  {}

  #[Route('/', name: 'samson_landing')]
  #[Route('/home', name: 'samson_home')]
  public function home(Request $request): Response
  {
    return $this->render('samson/home.html.twig');
  }

  #[Route('/bio', name: 'samson_bio')]
  public function bio(Request $request): Response
  {
    return $this->render('samson/bio.html.twig');
  }

  #[Route('/media', name: 'samson_media')]
  public function media(Request $request): Response
  {
    return $this->render('samson/media.html.twig');
  }

  // TODO: store and map individual media pages
  #[Route('/media/{id}', name: 'samson_media_view')]
  public function view_media(
    Request $request,
    string $id)
    : Response
  {
    // TODO return a better 404
    return $this->render("samson/media/$id.html.twig");
  }
}
