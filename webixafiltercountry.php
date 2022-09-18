<?php
/**
 * After install clean prestashop cache
 */

use PrestaShop\PrestaShop\Core\Domain\Customer\Exception\CustomerException;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\DataColumn;
use Symfony\Component\Translation\TranslatorInterface;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\ToggleColumn;
use PrestaShop\PrestaShop\Core\Grid\Definition\GridDefinitionInterface;
use PrestaShop\PrestaShop\Core\Grid\Filter\Filter;
use PrestaShop\PrestaShop\Core\Search\Filters\CustomerFilters;

if (!defined('_PS_VERSION_')) {
    exit;
}

class WebixaFilterCountry extends Module
{
    private $translator;
    private $polandCountryId;

    public function __construct()
    {
        $this->name = 'webixafiltercountry';
        $this->version = '1.0.0';
        $this->author = 'Webixa sp. z o.';
        $this->need_instance = 0;
        $this->translator = $this->getTranslator();

        parent::__construct();

        $this->displayName = $this->l('Filter Country');
        $this->description = $this->l('Adds country column to clients listing');

        $this->polandCountryId = Country::getByIso('pl');

        $this->ps_versions_compliancy = [
            'min' => '1.7.6.0',
            'max' => _PS_VERSION_,
        ];
    }
    /**
     * This function is required in order to make module compatible with new translation system.
     *
     * @return bool
     */
    public function isUsingNewTranslationSystem()
    {
        return true;
    }

    /**
     * Install module and register hooks to allow grid modification.
     *
     * @see https://devdocs.prestashop.com/1.7/modules/concepts/hooks/use-hooks-on-modern-pages/
     *
     * @return bool
     */
    public function install()
    {
        return parent::install() &&
            $this->registerHook('actionCustomerGridDefinitionModifier') &&
            $this->registerHook('actionCustomerGridQueryBuilderModifier');
    }

    public function uninstall()
    {
        return parent::uninstall();
    }

    /**
     * Hook allows to modify Customers grid definition.
     * This hook is a right place to add/remove columns or actions (bulk, grid).
     *
     * @param array $params
     */
    public function hookActionCustomerGridDefinitionModifier(array $params)
    {
        /** @var GridDefinitionInterface $definition */
        $definition = $params['definition'];

        $definition
            ->getColumns()
            ->addAfter(
                'connect',
                (new DataColumn('country'))
                    ->setName($this->getTranslator()->trans('Country', [], 'Modules.Webixafiltercustomer.Admin'))
                    ->setOptions([
                        'field' => 'country',
                    ])
            )
        ;

        $definition->getFilters()->add(
            (new Filter('country', ChoiceType::class))
                ->setTypeOptions([
                    'choices' => $this->getChoicesOfCountry(),
                    'expanded' => false,
                    'multiple' => false,
                    'required' => false,
                    'choice_translation_domain' => false,
                ])
                ->setAssociatedColumn('country')
        );
    }

    public function getChoicesOfCountry()
    {
        $choices = [];
        $countries = Country::getCountries(Context::getContext()->language->id);
        foreach ($countries as $country) {
            if ($this->polandCountryId != $country['id_country']) {
                $choices[$country['name']] = (int)$country['id_country'];
            }
        }

        return $choices;
    }

    /**
     * Hook allows to modify Customers query builder and add custom sql statements.
     *
     * @param array $params
     */
    public function hookActionCustomerGridQueryBuilderModifier(array $params)
    {
        $searchQueryBuilder = $params['search_query_builder'];

        /** @var CustomerFilters $searchCriteria */
        $searchCriteria = $params['search_criteria'];

        $queryByCountry = false;
        foreach ($searchCriteria->getFilters() as $filterName => $filterValue) {
            if ('country' === $filterName && $filterValue != $polandCountryId) {
                $queryByCountry = true;
            }
        }

        if ($queryByCountry) {
            $searchQueryBuilder->addSelect(
                'ct.name AS `country`'
            );

            $searchQueryBuilder->add('join', [
                'c' => [
                    'joinType' => 'LEFT OUTER',
                    'joinTable' => '(SELECT address.id_country, country_lang.id_lang, country_lang.name, address.id_customer FROM `' . pSQL(_DB_PREFIX_) . 'address` AS address RIGHT JOIN ' . pSQL(_DB_PREFIX_) . 'country_lang AS country_lang ON address.`id_country` = country_lang.`id_country` WHERE country_lang.`id_lang` = 1)',
                    'joinAlias' => 'ct',
                    'joinCondition' => 'ct.`id_customer` = c.`id_customer`',
                ],
            ], true);


            if ('country' === $searchCriteria->getOrderBy()) {
                $searchQueryBuilder->orderBy('ct.`name`', $searchCriteria->getOrderWay());
            }

            foreach ($searchCriteria->getFilters() as $filterName => $filterValue) {
                if ('country' === $filterName) {
                    $searchQueryBuilder->andWhere('ct.`id_country` = :country_value');
                    $searchQueryBuilder->setParameter('country_value', $filterValue);
                }
            }

            $searchQueryBuilder->groupBy('c.`id_customer`');
        }
    }

}
