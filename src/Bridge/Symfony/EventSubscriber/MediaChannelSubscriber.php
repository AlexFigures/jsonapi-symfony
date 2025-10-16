<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Bridge\Symfony\EventSubscriber;

use AlexFigures\Symfony\Bridge\Symfony\Routing\Attribute\MediaChannel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class MediaChannelSubscriber implements EventSubscriberInterface
{
    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => 'onKernelController',
        ];
    }

    public function onKernelController(ControllerEvent $event): void
    {
        $controller = $event->getController();
        $request = $event->getRequest();

        if ($request->attributes->has(MediaChannel::REQUEST_ATTRIBUTE)) {
            return;
        }

        $attribute = $this->extractAttribute($controller);

        if ($attribute === null) {
            return;
        }

        $request->attributes->set(MediaChannel::REQUEST_ATTRIBUTE, $attribute->name);
    }

    /**
     * @param callable|array{object, string} $controller
     */
    private function extractAttribute(callable|array $controller): ?MediaChannel
    {
        if (is_array($controller)) {
            return $this->extractFromMethod($controller[0], $controller[1]);
        }

        if (is_object($controller)) {
            return $this->extractFromClass($controller);
        }

        return null;
    }

    private function extractFromMethod(object $instance, string $method): ?MediaChannel
    {
        $reflection = new \ReflectionMethod($instance, $method);
        $attribute = $this->instantiateAttribute($reflection->getAttributes(MediaChannel::class));
        if ($attribute !== null) {
            return $attribute;
        }

        return $this->extractFromClass($instance);
    }

    private function extractFromClass(object $instance): ?MediaChannel
    {
        $reflection = new \ReflectionClass($instance);

        return $this->instantiateAttribute($reflection->getAttributes(MediaChannel::class));
    }

    /**
     * @param list<\ReflectionAttribute<MediaChannel>> $attributes
     */
    private function instantiateAttribute(array $attributes): ?MediaChannel
    {
        if ($attributes === []) {
            return null;
        }

        return $attributes[0]->newInstance();
    }
}
