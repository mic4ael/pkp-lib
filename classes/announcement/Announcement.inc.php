<?php

/**
 * @defgroup announcement Announcement
 * Implements announcements that can be presented to website visitors.
 */

/**
 * @file classes/announcement/Announcement.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Announcement
 * @ingroup announcement
 *
 * @see AnnouncementDAO
 *
 * @brief Basic class describing a announcement.
 */

class Announcement extends \PKP\core\DataObject
{
    //
    // Get/set methods
    //
    /**
     * Get assoc ID for this annoucement.
     *
     * @return int
     */
    public function getAssocId()
    {
        return $this->getData('assocId');
    }

    /**
     * Set assoc ID for this annoucement.
     *
     * @param $assocId int
     */
    public function setAssocId($assocId)
    {
        $this->setData('assocId', $assocId);
    }

    /**
     * Get assoc type for this annoucement.
     *
     * @return int
     */
    public function getAssocType()
    {
        return $this->getData('assocType');
    }

    /**
     * Set assoc type for this annoucement.
     *
     * @param $assocType int
     */
    public function setAssocType($assocType)
    {
        $this->setData('assocType', $assocType);
    }

    /**
     * Get the announcement type of the announcement.
     *
     * @return int
     */
    public function getTypeId()
    {
        return $this->getData('typeId');
    }

    /**
     * Set the announcement type of the announcement.
     *
     * @param $typeId int
     */
    public function setTypeId($typeId)
    {
        $this->setData('typeId', $typeId);
    }

    /**
     * Get the announcement type name of the announcement.
     *
     * @return string|null
     */
    public function getAnnouncementTypeName()
    {
        $announcementTypeDao = DAORegistry::getDAO('AnnouncementTypeDAO'); /** @var AnnouncementTypeDAO $announcementTypeDao */
        $announcementType = $announcementTypeDao->getById($this->getData('typeId'));
        return $announcementType ? $announcementType->getLocalizedTypeName() : null;
    }

    /**
     * Get localized announcement title
     *
     * @return string
     */
    public function getLocalizedTitle()
    {
        return $this->getLocalizedData('title');
    }

    /**
     * Get full localized announcement title including type name
     *
     * @return string
     */
    public function getLocalizedTitleFull()
    {
        $typeName = $this->getAnnouncementTypeName();
        if (!empty($typeName)) {
            return $typeName . ': ' . $this->getLocalizedTitle();
        } else {
            return $this->getLocalizedTitle();
        }
    }

    /**
     * Get announcement title.
     *
     * @param $locale
     *
     * @return string
     */
    public function getTitle($locale)
    {
        return $this->getData('title', $locale);
    }

    /**
     * Set announcement title.
     *
     * @param $title string
     * @param $locale string
     */
    public function setTitle($title, $locale)
    {
        $this->setData('title', $title, $locale);
    }

    /**
     * Get localized short description
     *
     * @return string
     */
    public function getLocalizedDescriptionShort()
    {
        return $this->getLocalizedData('descriptionShort');
    }

    /**
     * Get announcement brief description.
     *
     * @param $locale string
     *
     * @return string
     */
    public function getDescriptionShort($locale)
    {
        return $this->getData('descriptionShort', $locale);
    }

    /**
     * Set announcement brief description.
     *
     * @param $descriptionShort string
     * @param $locale string
     */
    public function setDescriptionShort($descriptionShort, $locale)
    {
        $this->setData('descriptionShort', $descriptionShort, $locale);
    }

    /**
     * Get localized full description
     *
     * @return string
     */
    public function getLocalizedDescription()
    {
        return $this->getLocalizedData('description');
    }

    /**
     * Get announcement description.
     *
     * @param $locale string
     *
     * @return string
     */
    public function getDescription($locale)
    {
        return $this->getData('description', $locale);
    }

    /**
     * Set announcement description.
     *
     * @param $description string
     * @param $locale string
     */
    public function setDescription($description, $locale)
    {
        $this->setData('description', $description, $locale);
    }

    /**
     * Get announcement expiration date.
     *
     * @return date (YYYY-MM-DD)
     */
    public function getDateExpire()
    {
        return $this->getData('dateExpire');
    }

    /**
     * Set announcement expiration date.
     *
     * @param $dateExpire date (YYYY-MM-DD)
     */
    public function setDateExpire($dateExpire)
    {
        $this->setData('dateExpire', $dateExpire);
    }

    /**
     * Get announcement posted date.
     *
     * @return date (YYYY-MM-DD)
     */
    public function getDatePosted()
    {
        return date('Y-m-d', strtotime($this->getData('datePosted')));
    }

    /**
     * Get announcement posted datetime.
     *
     * @return datetime (YYYY-MM-DD HH:MM:SS)
     */
    public function getDatetimePosted()
    {
        return $this->getData('datePosted');
    }

    /**
     * Set announcement posted date.
     *
     * @param $datePosted date (YYYY-MM-DD)
     */
    public function setDatePosted($datePosted)
    {
        $this->setData('datePosted', $datePosted);
    }

    /**
     * Set announcement posted datetime.
     *
     * @param $datetimePosted date (YYYY-MM-DD HH:MM:SS)
     */
    public function setDatetimePosted($datetimePosted)
    {
        $this->setData('datePosted', $datetimePosted);
    }
}
