<?php

declare(strict_types=1);

/*
 * This file is part of the Sonata Project package.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonata\PageBundle\Tests\Page;

use PHPUnit\Framework\TestCase;
use Sonata\BlockBundle\Model\BlockInterface;
use Sonata\PageBundle\CmsManager\CmsSnapshotManager;
use Sonata\PageBundle\Exception\PageNotFoundException;
use Sonata\PageBundle\Model\Block;
use Sonata\PageBundle\Model\BlockInteractorInterface;
use Sonata\PageBundle\Model\PageInterface;
use Sonata\PageBundle\Model\SiteInterface;
use Sonata\PageBundle\Model\SnapshotInterface;
use Sonata\PageBundle\Model\SnapshotManagerInterface;
use Sonata\PageBundle\Model\SnapshotPageProxyInterface;
use Sonata\PageBundle\Model\TransformerInterface;
use Sonata\PageBundle\Tests\Model\Page;

class SnapshotBlock extends Block
{
    public function setId($id)
    {
    }

    public function getId()
    {
    }
}

class CmsSnapshotManagerTest extends TestCase
{
    /**
     * @var CmsSnapshotManager
     */
    protected $manager;

    protected $blockInteractor;

    protected $snapshotManager;

    protected $transformer;

    /**
     * Setup manager object to test.
     */
    public function setUp()
    {
        $this->blockInteractor = $this->getMockBlockInteractor();
        $this->snapshotManager = $this->createMock(SnapshotManagerInterface::class);
        $this->transformer = $this->createMock(TransformerInterface::class);
        $this->manager = new CmsSnapshotManager($this->snapshotManager, $this->transformer);
    }

    /**
     * Test finding an existing container in a page.
     */
    public function testFindExistingContainer()
    {
        $block = new SnapshotBlock();
        $block->setSettings(['code' => 'findme']);

        $page = new Page();
        $page->addBlocks($block);

        $container = $this->manager->findContainer('findme', $page);

        $this->assertSame(
            spl_object_hash($block),
            spl_object_hash($container),
            'should retrieve the block of the page'
        );
    }

    /**
     * Test finding an non-existing container in a page does NOT create a new block.
     */
    public function testFindNonExistingContainerCreatesNoNewBlock()
    {
        $page = new Page();

        $container = $this->manager->findContainer('newcontainer', $page);

        $this->assertNull($container, 'should not create a new container block');
    }

    public function testGetPageWithUnknownPage()
    {
        $this->expectException(PageNotFoundException::class);

        $this->snapshotManager->expects($this->once())->method('findEnableSnapshot')->willReturn(null);

        $site = $this->createMock(SiteInterface::class);

        $snapshotManager = new CmsSnapshotManager($this->snapshotManager, $this->transformer);

        $snapshotManager->getPage($site, 1);
    }

    public function testGetPageWithId()
    {
        $cBlock = $this->createMock(BlockInterface::class);
        $cBlock->expects($this->any())->method('hasChildren')->willReturn(false);
        $cBlock->expects($this->any())->method('getId')->willReturn(2);

        $pBlock = $this->createMock(BlockInterface::class);
        $pBlock->expects($this->any())->method('getChildren')->willReturn([$cBlock]);
        $pBlock->expects($this->any())->method('hasChildren')->willReturn(true);
        $pBlock->expects($this->any())->method('getId')->willReturn(1);

        $page = $this->createMock(PageInterface::class);
        $page->expects($this->any())->method('getBlocks')->willReturnCallback(static function () use ($pBlock) {
            static $count;

            ++$count;

            if (1 === $count) {
                return [];
            }

            return [$pBlock];
        });

        $snapshot = $this->createMock(SnapshotInterface::class);
        $snapshot->expects($this->once())->method('getContent')->willReturn([
            // we don't care here about real values, the mock transformer will return the valid $pBlock instance
            'blocks' => [],
        ]);

        $this->snapshotManager
            ->expects($this->once())
            ->method('findEnableSnapshot')
            ->willReturn($snapshot);

        $this->transformer->expects($this->once())->method('load')->willReturn($page);

        $site = $this->createMock(SiteInterface::class);

        $snapshotManager = new CmsSnapshotManager($this->snapshotManager, $this->transformer);

        $page = $snapshotManager->getPage($site, 1);

        $this->assertInstanceOf(SnapshotPageProxyInterface::class, $page);

        $this->assertInstanceOf(BlockInterface::class, $snapshotManager->getBlock(1));
        $this->assertInstanceOf(BlockInterface::class, $snapshotManager->getBlock(2));
    }

    /**
     * Returns a mock block interactor.
     *
     * @return BlockInteractorInterface
     */
    protected function getMockBlockInteractor()
    {
        $callback = static function ($options) {
            $block = new SnapshotBlock();
            $block->setSettings($options);

            return $block;
        };

        $blockInteractor = $this->createMock(BlockInteractorInterface::class);
        $blockInteractor
            ->expects($this->any())
            ->method('createNewContainer')
            ->willReturnCallback($callback);

        return $blockInteractor;
    }
}
