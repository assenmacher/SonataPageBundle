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

namespace Sonata\PageBundle\Form\Type;

use Sonata\AdminBundle\Form\Type\ModelType;
use Sonata\PageBundle\Model\PageInterface;
use Sonata\PageBundle\Model\PageManagerInterface;
use Sonata\PageBundle\Model\SiteInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * Select a page.
 *
 * @author Thomas Rabaix <thomas.rabaix@sonata-project.org>
 */
class PageSelectorType extends AbstractType
{
    /**
     * @var PageManagerInterface
     */
    protected $manager;

    /**
     * @param PageManagerInterface $manager
     */
    public function __construct(PageManagerInterface $manager)
    {
        $this->manager = $manager;
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $that = $this;

        $resolver->setDefaults([
            'page' => null,
            'site' => null,
            'choices' => static function (Options $opts, $previousValue) use ($that) {
                return $that->getChoices($opts);
            },
            'choice_translation_domain' => false,
            'filter_choice' => [
                'current_page' => false,
                'request_method' => 'GET',
                'dynamic' => true,
                'hierarchy' => 'all',
            ],
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $this->configureOptions($resolver);
    }

    /**
     * @param Options $options
     *
     * @return array
     */
    public function getChoices(Options $options)
    {
        if (!$options['site'] instanceof SiteInterface) {
            return [];
        }

        $filter_choice = array_merge([
            'current_page' => false,
            'request_method' => 'GET',
            'dynamic' => true,
            'hierarchy' => 'all',
        ], $options['filter_choice']);

        $pages = $this->manager->loadPages($options['site']);

        $choices = [];

        foreach ($pages as $page) {
            // internal cannot be selected
            if ($page->isInternal()) {
                continue;
            }

            if (!$filter_choice['current_page'] && $options['page'] && $options['page']->getId() === $page->getId())
            {
                continue;
            }

            if($page->getParent())
            {
                continue;
            }

            if ('all' !== $filter_choice['dynamic'] && (
                    ($filter_choice['dynamic'] && $page->isDynamic()) ||
                    (!$filter_choice['dynamic'] && !$page->isDynamic())
                )
            ) {
                continue;
            }

            if ('all' !== $filter_choice['request_method'] && !$page->hasRequestMethod($filter_choice['request_method'])) {
                continue;
            }

            $choices[$page->getId()] = $page;

            $this->childWalker($page, $options['page'], $choices, $filter_choice);
        }

        return $choices;
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return ModelType::class;
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'sonata_page_selector';
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->getBlockPrefix();
    }

    /**
     * @param PageInterface $page
     * @param PageInterface $currentPage
     * @param array         $choices
     * @param int           $level
     */
    private function childWalker(PageInterface $page, PageInterface $currentPage = null, &$choices, $filter_choice, $level = 1)
    {
        foreach ($page->getChildren() as $child) {
            if (!$filter_choice['current_page'] && $currentPage && $currentPage->getId() === $child->getId()) {
                continue;
            }

            if ('all' !== $filter_choice['dynamic'] && (
                    ($filter_choice['dynamic'] && $child->isDynamic()) ||
                    (!$filter_choice['dynamic'] && !$child->isDynamic())
                )
            ) {
                continue;
            }

            if ('all' !== $filter_choice['request_method'] && !$child->hasRequestMethod($filter_choice['request_method'])) {
                continue;
            }

            $choices[$child->getId()] = $child->setLevel($level);

            $this->childWalker($child, $currentPage, $choices, $filter_choice, $level + 1);
        }
    }
}
