<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\ListPrice;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemListPriceRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @group rules
 */
class LineItemListPriceRuleTest extends TestCase
{
    /**
     * @var LineItemListPriceRule
     */
    private $rule;

    protected function setUp(): void
    {
        $this->rule = new LineItemListPriceRule();
    }

    public function testGetName(): void
    {
        static::assertEquals('cartLineItemListPrice', $this->rule->getName());
    }

    public function testGetConstraints(): void
    {
        $ruleConstraints = $this->rule->getConstraints();

        static::assertArrayHasKey('amount', $ruleConstraints, 'Rule Constraint amount is not defined');
        static::assertArrayHasKey('operator', $ruleConstraints, 'Rule Constraint operator is not defined');
    }

    /**
     * @dataProvider getMatchingRuleTestData
     */
    public function testIfMatchesCorrectWithLineItem(string $operator, float $amount, float $lineItemAmount, bool $expected): void
    {
        $this->rule->assign([
            'amount' => $amount,
            'operator' => $operator,
        ]);

        $match = $this->rule->match(new LineItemScope(
            $this->createLineItem($lineItemAmount),
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertEquals($expected, $match);
    }

    public function getMatchingRuleTestData(): array
    {
        return [
            // OPERATOR_EQ
            'match / operator equals / same price' => [Rule::OPERATOR_EQ, 100, 100, true],
            'no match / operator equals / different price' => [Rule::OPERATOR_EQ, 200, 100, false],
            // OPERATOR_NEQ
            'no match / operator not equals / same price' => [Rule::OPERATOR_NEQ, 100, 100, false],
            'match / operator not equals / different price' => [Rule::OPERATOR_NEQ, 200, 100, true],
            // OPERATOR_GT
            'no match / operator greater than / lower price' => [Rule::OPERATOR_GT, 100, 50, false],
            'no match / operator greater than / same price' => [Rule::OPERATOR_GT, 100, 100, false],
            'match / operator greater than / higher price' => [Rule::OPERATOR_GT, 100, 200, true],
            // OPERATOR_GTE
            'no match / operator greater than equals / lower price' => [Rule::OPERATOR_GTE, 100, 50, false],
            'match / operator greater than equals / same price' => [Rule::OPERATOR_GTE, 100, 100, true],
            'match / operator greater than equals / higher price' => [Rule::OPERATOR_GTE, 100, 200, true],
            // OPERATOR_LT
            'match / operator lower than / lower price' => [Rule::OPERATOR_LT, 100, 50, true],
            'no match / operator lower  than / same price' => [Rule::OPERATOR_LT, 100, 100, false],
            'no match / operator lower than / higher price' => [Rule::OPERATOR_LT, 100, 200, false],
            // OPERATOR_LTE
            'match / operator lower than equals / lower price' => [Rule::OPERATOR_LTE, 100, 50, true],
            'match / operator lower than equals / same price' => [Rule::OPERATOR_LTE, 100, 100, true],
            'no match / operator lower than equals / higher price' => [Rule::OPERATOR_LTE, 100, 200, false],
        ];
    }

    /**
     * @dataProvider getCartRuleScopeTestData
     */
    public function testIfMatchesCorrectWithCartRuleScope(string $operator, float $amount, float $lineItemAmount1, float $lineItemAmount2, bool $expected): void
    {
        $this->rule->assign([
            'amount' => $amount,
            'operator' => $operator,
        ]);

        $cart = new Cart('test', Uuid::randomHex());

        $lineItemCollection = new LineItemCollection();
        $lineItemCollection->add($this->createLineItem($lineItemAmount1));
        $lineItemCollection->add($this->createLineItem($lineItemAmount2));

        $cart->setLineItems($lineItemCollection);

        $match = $this->rule->match(new CartRuleScope(
            $cart,
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertEquals($expected, $match);
    }

    public function getCartRuleScopeTestData(): array
    {
        return [
            // OPERATOR_EQ
            'match / operator equals / same price' => [Rule::OPERATOR_EQ, 100, 100, 200, true],
            'no match / operator equals / different price' => [Rule::OPERATOR_EQ, 200, 100, 300, false],
            // OPERATOR_NEQ
            'no match / operator not equals / same prices' => [Rule::OPERATOR_NEQ, 100, 100, 100, false],
            'match / operator not equals / different prices' => [Rule::OPERATOR_NEQ, 200, 100, 200, true],
            'match / operator not equals / different prices 2' => [Rule::OPERATOR_NEQ, 200, 100, 300, true],
            // OPERATOR_GT
            'no match / operator greater than / lower price' => [Rule::OPERATOR_GT, 100, 50, 70, false],
            'no match / operator greater than / same price' => [Rule::OPERATOR_GT, 100, 100, 70, false],
            'match / operator greater than / higher price' => [Rule::OPERATOR_GT, 100, 200, 70, true],
            // OPERATOR_GTE
            'no match / operator greater than equals / lower price' => [Rule::OPERATOR_GTE, 100, 50, 70, false],
            'match / operator greater than equals / same price' => [Rule::OPERATOR_GTE, 100, 100, 70, true],
            'match / operator greater than equals / higher price' => [Rule::OPERATOR_GTE, 100, 200, 70, true],
            // OPERATOR_LT
            'match / operator lower than / lower price' => [Rule::OPERATOR_LT, 100, 50, 120, true],
            'no match / operator lower  than / same price' => [Rule::OPERATOR_LT, 100, 100, 120, false],
            'no match / operator lower than / higher price' => [Rule::OPERATOR_LT, 100, 200, 120, false],
            // OPERATOR_LTE
            'match / operator lower than equals / lower price' => [Rule::OPERATOR_LTE, 100, 50, 120, true],
            'match / operator lower than equals / same price' => [Rule::OPERATOR_LTE, 100, 100, 120, true],
            'no match / operator lower than equals / higher price' => [Rule::OPERATOR_LTE, 100, 200, 120, false],
        ];
    }

    /**
     * @throws InvalidQuantityException
     */
    public function testMatchWithEmptyCalculatedPrice(): void
    {
        $this->rule->assign(['amount' => 100, 'operator' => Rule::OPERATOR_EQ]);

        $match = $this->rule->match(new LineItemScope(
            new LineItem('dummy-article', 'product', null, 3),
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertFalse($match);
    }

    /**
     * @throws InvalidQuantityException
     */
    public function testMatchWithEmptyListPrice(): void
    {
        $price = 100;

        $this->rule->assign(['amount' => $price, 'operator' => Rule::OPERATOR_EQ]);

        $calculatedPrice = new CalculatedPrice($price, $price, new CalculatedTaxCollection(), new TaxRuleCollection(), 1, null, null);

        $lineItem = (new LineItem(Uuid::randomHex(), 'product', null, 3))->setPrice($calculatedPrice);

        $match = $this->rule->match(new LineItemScope(
            $lineItem,
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertFalse($match);
    }

    /**
     * @throws InvalidQuantityException
     */
    private function createLineItem(float $listPriceAmount): LineItem
    {
        $listPrice = ListPrice::createFromUnitPrice(400, $listPriceAmount);

        $calculatedPrice = new CalculatedPrice($listPriceAmount, $listPriceAmount, new CalculatedTaxCollection(), new TaxRuleCollection(), 1, null, $listPrice);

        return (new LineItem(Uuid::randomHex(), 'product', null, 3))->setPrice($calculatedPrice);
    }
}