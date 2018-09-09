<?php

class DomainParameter extends DataObject
{

	private static $db = array(
		'SourceDomain' => 'Varchar(255)',
		'Parameters' => 'Varchar(255)'
	);

	private static $default_sort = 'SourceDomain ASC';

	private static $searchable_fields = array(
		'SourceDomain'
	);

	private static $summary_fields = array(
		'SourceDomain',
		'Parameters'
	);

	private static $field_labels = array(
		'SourceDomain' => 'Domain',
		'Parameters' => 'Parameters To Add'
	);

	private static $priority = 'ASC';

	/**
	 *    CMS users with appropriate access may view any mappings.
	 */
	public function canView($member = null)
	{

		return true;
	}

	/**
	 *    CMS administrators may edit any mappings.
	 */
	public function canEdit($member = null)
	{

		return Permission::checkMember($member, 'ADMIN');
	}

	/**
	 *    CMS administrators may create any mappings.
	 */
	public function canCreate($member = null)
	{

		return Permission::checkMember($member, 'ADMIN');
	}

	/**
	 *    CMS administrators may delete any mappings.
	 */
	public function canDelete($member = null)
	{

		return Permission::checkMember($member, 'ADMIN');
	}

	public function getTitle()
	{

		return $this->SourceDomain;
	}

	public function getCMSFields()
	{

		$fields = parent::getCMSFields();
		Requirements::css(MISDIRECTION_PATH . '/css/misdirection.css');

		// Remove any fields that are not required in their default state.
		$fields->removeByName('SourceDomain');
		$fields->removeByName('Parameters');

		// Instantiate the required fields.
		$fields->insertBefore(HeaderField::create(
			'MappedLinkHeader',
			'Add parameters to the request for a specifically redirected domain',
			3
		), 'SourceDomain');

		$domain = TextField::create('SourceDomain', '')
			->setRightTitle('This should <strong>not</strong> include the <strong>HTTP/S</strong> scheme')
			->setTitle('Domain');
		$fields->addFieldToTab('Root.Main', $domain);

		$parameters = TextField::create('Parameters', '')
			->setRightTitle('These parameters will be added to the final request, for instance: rf=1 or rf=1&x=y')
			->setTitle('Parameters');
		$fields->addFieldToTab('Root.Main', $parameters);

		// Allow extension customisation.
		$this->extend('updateSourceDomainCMSFields', $fields);

		return $fields;
	}

	public function validate()
	{

		$result = parent::validate();

		// Allow extension customisation.
		$this->extend('validateSourceDomainMapping', $result);

		return $result;
	}

	public function onBeforeWrite()
	{
		parent::onBeforeWrite();
		$this->SourceDomain = strtolower($this->SourceDomain);
	}

}
