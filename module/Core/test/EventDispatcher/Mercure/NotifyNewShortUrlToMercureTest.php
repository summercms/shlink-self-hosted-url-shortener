<?php

declare(strict_types=1);

namespace ShlinkioTest\Shlink\Core\EventDispatcher\Mercure;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shlinkio\Shlink\Common\UpdatePublishing\PublishingHelperInterface;
use Shlinkio\Shlink\Common\UpdatePublishing\Update;
use Shlinkio\Shlink\Core\EventDispatcher\Event\ShortUrlCreated;
use Shlinkio\Shlink\Core\EventDispatcher\Mercure\NotifyNewShortUrlToMercure;
use Shlinkio\Shlink\Core\EventDispatcher\PublishingUpdatesGeneratorInterface;
use Shlinkio\Shlink\Core\ShortUrl\Entity\ShortUrl;

class NotifyNewShortUrlToMercureTest extends TestCase
{
    private NotifyNewShortUrlToMercure $listener;
    private MockObject $helper;
    private MockObject $updatesGenerator;
    private MockObject $em;
    private MockObject $logger;

    protected function setUp(): void
    {
        $this->helper = $this->createMock(PublishingHelperInterface::class);
        $this->updatesGenerator = $this->createMock(PublishingUpdatesGeneratorInterface::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->listener = new NotifyNewShortUrlToMercure(
            $this->helper,
            $this->updatesGenerator,
            $this->em,
            $this->logger,
        );
    }

    /** @test */
    public function messageIsLoggedWhenShortUrlIsNotFound(): void
    {
        $this->em->expects($this->once())->method('find')->with(
            $this->equalTo(ShortUrl::class),
            $this->equalTo('123'),
        )->willReturn(null);
        $this->helper->expects($this->never())->method('publishUpdate');
        $this->updatesGenerator->expects($this->never())->method('newShortUrlUpdate');
        $this->logger->expects($this->once())->method('warning')->with(
            $this->equalTo('Tried to notify {name} for new short URL with id "{shortUrlId}", but it does not exist.'),
            $this->equalTo(['shortUrlId' => '123', 'name' => 'Mercure']),
        );
        $this->logger->expects($this->never())->method('debug');

        ($this->listener)(new ShortUrlCreated('123'));
    }

    /** @test */
    public function expectedNotificationIsPublished(): void
    {
        $shortUrl = ShortUrl::withLongUrl('');
        $update = Update::forTopicAndPayload('', []);

        $this->em->expects($this->once())->method('find')->with(
            $this->equalTo(ShortUrl::class),
            $this->equalTo('123'),
        )->willReturn($shortUrl);
        $this->updatesGenerator->expects($this->once())->method('newShortUrlUpdate')->with(
            $this->equalTo($shortUrl),
        )->willReturn($update);
        $this->helper->expects($this->once())->method('publishUpdate')->with($this->equalTo($update));
        $this->logger->expects($this->never())->method('warning');
        $this->logger->expects($this->never())->method('debug');

        ($this->listener)(new ShortUrlCreated('123'));
    }

    /** @test */
    public function messageIsPrintedIfPublishingFails(): void
    {
        $shortUrl = ShortUrl::withLongUrl('');
        $update = Update::forTopicAndPayload('', []);
        $e = new Exception('Error');

        $this->em->expects($this->once())->method('find')->with(
            $this->equalTo(ShortUrl::class),
            $this->equalTo('123'),
        )->willReturn($shortUrl);
        $this->updatesGenerator->expects($this->once())->method('newShortUrlUpdate')->with(
            $this->equalTo($shortUrl),
        )->willReturn($update);
        $this->helper->expects($this->once())->method('publishUpdate')->with(
            $this->equalTo($update),
        )->willThrowException($e);
        $this->logger->expects($this->never())->method('warning');
        $this->logger->expects($this->once())->method('debug')->with(
            $this->equalTo('Error while trying to notify {name} with new short URL. {e}'),
            $this->equalTo(['e' => $e, 'name' => 'Mercure']),
        );

        ($this->listener)(new ShortUrlCreated('123'));
    }
}