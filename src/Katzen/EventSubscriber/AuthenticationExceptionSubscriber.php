<?php

namespace App\Katzen\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * Redirect unauthenticated users to login page when they try to access protected resources
 */
class AuthenticationExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 10],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $request = $event->getRequest();
        
        if ($exception instanceof \TypeError) {
            $message = $exception->getMessage();
            
            // Check if the error is related to KatzenUser being null
            if (str_contains($message, 'KatzenUser') && 
                str_contains($message, 'null given')) {
                
                $targetPath = $request->getUri();
                
                $loginUrl = $this->urlGenerator->generate('app_login', [
                    '_target_path' => $targetPath
                ]);
                
                $response = new RedirectResponse($loginUrl);
                $event->setResponse($response);
            }
        }
        
        // Handle explicit authentication exceptions
        if ($exception instanceof AuthenticationException || 
            $exception instanceof AccessDeniedHttpException) {
            
            $targetPath = $request->getUri();
            $loginUrl = $this->urlGenerator->generate('app_login', [
                '_target_path' => $targetPath
            ]);
            
            $response = new RedirectResponse($loginUrl);
            $event->setResponse($response);
        }
    }
}
