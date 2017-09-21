<?php
namespace Neos\ContentRepository\Tests\Unit\Domain\Model\InterDimension;

/*
 * This file is part of the Neos.ContentRepository package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */
use Neos\ContentRepository\Domain\Context\Dimension;
use Neos\ContentRepository\Domain\Context\DimensionSpace;
use Neos\Flow\Tests\UnitTestCase;
use Neos\Utility\ObjectAccess;

/**
 * Test cases for the inter dimensional fallback graph
 */
class InterDimensionalFallbackGraphTest extends UnitTestCase
{
    /**
     * @test
     */
    public function createContentSubgraphRegistersSubgraph()
    {
        $graph = new DimensionSpace\Repository\InterDimensionalFallbackGraph();

        $contentSubgraph = $graph->createContentSubgraph(['test' => new Dimension\Model\ContentDimensionValue('a')]);

        $this->assertSame($contentSubgraph, $graph->getSubgraphByDimensionSpacePointHash($contentSubgraph->getIdentityHash()));
    }

    /**
     * @test
     */
    public function connectSubgraphsAddsFallbackToVariant()
    {
        $contentDimension = new Dimension\Repository\ContentDimension('test');
        $fallbackValue = $contentDimension->createValue('a');
        $variantValue = $contentDimension->createValue('b', $fallbackValue);
        #$graph = new DimensionCombination\Repository\InterDimensionalFallbackGraph([$contentDimension]);
        $graph = new DimensionSpace\Repository\InterDimensionalFallbackGraph();

        $fallback = new DimensionSpace\Repository\ContentSubgraph(['test' => $fallbackValue]);
        $variant = new DimensionSpace\Repository\ContentSubgraph(['test' => $variantValue]);

        $graph->connectSubgraphs($variant, $fallback);

        $this->assertContains($fallback, $variant->getFallback());
    }

    /**
     * @test
     */
    public function connectSubgraphsAddsVariantToFallback()
    {
        $contentDimension = new Dimension\Repository\ContentDimension('test');
        $fallbackValue = $contentDimension->createValue('a');
        $variantValue = $contentDimension->createValue('b', $fallbackValue);
        #$graph = new DimensionCombination\Repository\InterDimensionalFallbackGraph([$contentDimension]);
        $graph = new DimensionSpace\Repository\InterDimensionalFallbackGraph();

        $fallback = new DimensionSpace\Repository\ContentSubgraph(['test' => $fallbackValue]);
        $variant = new DimensionSpace\Repository\ContentSubgraph(['test' => $variantValue]);

        $graph->connectSubgraphs($variant, $fallback);

        $this->assertContains($variant, $fallback->getVariants());
    }

    /**
     * @test
     * @dataProvider dimensionValueCombinationProvider
     * @param array $variantDimensionCombination
     * @param array $fallbackDimensionCombination
     * @param array $expectedWeight
     */
    public function calculateFallbackWeightAggregatesCorrectWeightPerDimension(array $variantDimensionCombination, array $fallbackDimensionCombination, array $expectedWeight)
    {
        $intraGraph = new Dimension\Repository\IntraDimensionalFallbackGraph();

        $availableDimensionValues = [];

        $primaryDimension = $intraGraph->createDimension('primary');
        $availableDimensionValues['primary0'] = $primaryDimension->createValue('0');
        $availableDimensionValues['primary1'] = $primaryDimension->createValue('1', $availableDimensionValues['primary0']);
        $availableDimensionValues['primary2'] = $primaryDimension->createValue('2', $availableDimensionValues['primary1']);

        $secondaryDimension = $intraGraph->createDimension('secondary');
        $availableDimensionValues['secondary0a'] = $secondaryDimension->createValue('0a');
        $availableDimensionValues['secondary0b'] = $secondaryDimension->createValue('0b');

        $tertiaryDimension = $intraGraph->createDimension('tertiary');
        $availableDimensionValues['tertiary0'] = $tertiaryDimension->createValue('0');
        $availableDimensionValues['tertiary1a'] = $tertiaryDimension->createValue('1a', $availableDimensionValues['tertiary0']);
        $availableDimensionValues['tertiary1b'] = $tertiaryDimension->createValue('1b', $availableDimensionValues['tertiary0']);

        #$interGraph = new DimensionCombination\Repository\InterDimensionalFallbackGraph([$primaryDimension, $secondaryDimension, $tertiaryDimension]);
        $interGraph = new DimensionSpace\Repository\InterDimensionalFallbackGraph();

        array_walk($variantDimensionCombination, function (&$value) use ($availableDimensionValues) {
            $value = $availableDimensionValues[$value];
        });
        $variantContentSubgraph = new DimensionSpace\Repository\ContentSubgraph($variantDimensionCombination);

        array_walk($fallbackDimensionCombination, function (&$value) use ($availableDimensionValues) {
            $value = $availableDimensionValues[$value];
        });
        $fallbackContentSubgraph = new DimensionSpace\Repository\ContentSubgraph($fallbackDimensionCombination);

        $this->assertSame($expectedWeight, $interGraph->calculateFallbackWeight($variantContentSubgraph, $fallbackContentSubgraph));
    }

    public function dimensionValueCombinationProvider()
    {
        return [
            [
                ['primary' => 'primary2', 'secondary' => 'secondary0a', 'tertiary' => 'tertiary1a'],
                ['primary' => 'primary0', 'secondary' => 'secondary0a', 'tertiary' => 'tertiary0'],
                ['primary' => 2, 'secondary' => 0, 'tertiary' => 1]
            ],
            [
                ['primary' => 'primary1', 'secondary' => 'secondary0b', 'tertiary' => 'tertiary0'],
                ['primary' => 'primary0', 'secondary' => 'secondary0b', 'tertiary' => 'tertiary0'],
                ['primary' => 1, 'secondary' => 0, 'tertiary' => 0]
            ],
            [
                ['primary' => 'primary0', 'secondary' => 'secondary0b', 'tertiary' => 'tertiary1b'],
                ['primary' => 'primary0', 'secondary' => 'secondary0b', 'tertiary' => 'tertiary0'],
                ['primary' => 0, 'secondary' => 0, 'tertiary' => 1]
            ]
        ];
    }

    /**
     * @test
     */
    public function determineWeightNormalizationBaseEvaluatesToMaximumDimensionDepthPlusOne()
    {
        $firstDimension = new Dimension\Repository\ContentDimension('first');
        $firstDepth = random_int(0, 100);
        ObjectAccess::setProperty($firstDimension, 'depth', $firstDepth, true);

        $secondDimension = new Dimension\Repository\ContentDimension('second');
        $secondDepth = random_int(0, 100);
        ObjectAccess::setProperty($secondDimension, 'depth', $secondDepth, true);

        #$graph = new DimensionCombination\Repository\InterDimensionalFallbackGraph([$firstDimension, $secondDimension]);
        $graph = new DimensionSpace\Repository\InterDimensionalFallbackGraph();
        $this->assertSame(max($firstDepth, $secondDepth) + 1, $graph->determineWeightNormalizationBase());
    }


    /**
     * @test
     * @dataProvider variationEdgeWeightNormalizationProvider
     * @param int $dimensionDepth
     * @param array $weight
     * @param int $expectedNormalizedWeight
     */
    public function normalizeWeightCorrectlyCalculatesNormalizedWeight(int $dimensionDepth, array $weight, int $expectedNormalizedWeight)
    {
        $primaryDimension = new Dimension\Repository\ContentDimension('primary');
        ObjectAccess::setProperty($primaryDimension, 'depth', $dimensionDepth, true);
        $secondaryDimension = new Dimension\Repository\ContentDimension('secondary');
        ObjectAccess::setProperty($secondaryDimension, 'depth', $dimensionDepth, true);
        $tertiaryDimension = new Dimension\Repository\ContentDimension('tertiary');
        ObjectAccess::setProperty($tertiaryDimension, 'depth', $dimensionDepth, true);

        #$graph = new DimensionCombination\Repository\InterDimensionalFallbackGraph([$primaryDimension, $secondaryDimension, $tertiaryDimension]);
        $graph = new DimensionSpace\Repository\InterDimensionalFallbackGraph();

        $variant = new DimensionSpace\Repository\ContentSubgraph([]);
        $fallback = new DimensionSpace\Repository\ContentSubgraph([]);
        $variationEdge = new DimensionSpace\Repository\VariationEdge($variant, $fallback, $weight);

        $this->assertSame($expectedNormalizedWeight, $graph->normalizeWeight($variationEdge->getWeight()));
    }

    public function variationEdgeWeightNormalizationProvider()
    {
        return [
            [5, ['primary' => 5, 'secondary' => 4, 'tertiary' => 0], 204],
            [6, ['primary' => 0, 'secondary' => 3, 'tertiary' => 6], 27],
            [3, ['primary' => 1, 'secondary' => 3, 'tertiary' => 0], 28],
        ];
    }

    /**
     * @test
     * @dataProvider fallbackPrioritizationProvider
     * @param array $primaryFallbackWeight
     * @param array $secondaryFallbackWeight
     */
    public function getPrimaryFallbackReturnsFallbackWithLowestNormalizedWeight($primaryFallbackWeight, $secondaryFallbackWeight)
    {
        $primaryDimension = new Dimension\Repository\ContentDimension('primary');
        $primaryVariantValue = $primaryDimension->createValue('variant');
        $primaryDummyValue1 = $primaryDimension->createValue('dummy1');
        $primaryDummyValue2 = $primaryDimension->createValue('dummy2');
        ObjectAccess::setProperty($primaryDimension, 'depth', 5, true);
        $secondaryDimension = new Dimension\Repository\ContentDimension('secondary');
        $secondaryDummyValue = $secondaryDimension->createValue('dummy');
        ObjectAccess::setProperty($secondaryDimension, 'depth', 5, true);
        $tertiaryDimension = new Dimension\Repository\ContentDimension('tertiary');
        $tertiaryDummyValue = $tertiaryDimension->createValue('dummy');
        ObjectAccess::setProperty($tertiaryDimension, 'depth', 5, true);

        #$graph = new DimensionCombination\Repository\InterDimensionalFallbackGraph([$primaryDimension, $secondaryDimension, $tertiaryDimension]);
        $graph = new DimensionSpace\Repository\InterDimensionalFallbackGraph();

        $variant = new DimensionSpace\Repository\ContentSubgraph([
            'primary' => $primaryVariantValue,
            'secondary' => $secondaryDummyValue,
            'tertiary' => $tertiaryDummyValue
        ]);
        $primaryFallback = new DimensionSpace\Repository\ContentSubgraph([
            'primary' => $primaryDummyValue1,
            'secondary' => $secondaryDummyValue,
            'tertiary' => $tertiaryDummyValue
        ]);
        $secondaryFallback = new DimensionSpace\Repository\ContentSubgraph([
            'primary' => $primaryDummyValue2,
            'secondary' => $secondaryDummyValue,
            'tertiary' => $tertiaryDummyValue
        ]);
        new DimensionSpace\Repository\VariationEdge($variant, $primaryFallback, $primaryFallbackWeight);
        new DimensionSpace\Repository\VariationEdge($variant, $secondaryFallback, $secondaryFallbackWeight);

        $this->assertSame($primaryFallback, $graph->getPrimaryFallback($variant));
    }

    public function fallbackPrioritizationProvider()
    {
        return [
            [
                ['primary' => 0, 'secondary' => 0, 'tertiary' => 1],
                ['primary' => 0, 'secondary' => 0, 'tertiary' => 2]
            ],
            [
                ['primary' => 0, 'secondary' => 0, 'tertiary' => 5],
                ['primary' => 0, 'secondary' => 1, 'tertiary' => 0]
            ],
            [
                ['primary' => 0, 'secondary' => 5, 'tertiary' => 5],
                ['primary' => 1, 'secondary' => 0, 'tertiary' => 0]
            ]
        ];
    }
}