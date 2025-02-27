<?php
/**
 * Duplicated from /vendor/craftcms/cms/src/gql/resolvers/elements/Asset.php
 */

namespace jamesedmonston\graphqlauthentication\resolvers;

use Craft;
use craft\db\Table;
use craft\elements\Asset as AssetElement;
use craft\gql\base\ElementResolver;
use craft\helpers\Db;
use craft\helpers\Gql as GqlHelper;
use craft\services\Volumes;
use jamesedmonston\graphqlauthentication\GraphqlAuthentication;

/**
 * Class Asset
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.3.0
 */
class Asset extends ElementResolver
{
    /**
     * @inheritdoc
     */
    public static function prepareQuery($source, array $arguments, $fieldName = null)
    {
        // If this is the beginning of a resolver chain, start fresh
        if ($source === null) {
            $query = AssetElement::find();
            // If not, get the prepared element query
        } else {
            $query = $source->$fieldName;
        }

        // If it's preloaded, it's preloaded.
        if (is_array($query)) {
            return $query;
        }

        $restrictionService = GraphqlAuthentication::$restrictionService;

        if ($restrictionService->shouldRestrictRequests()) {
            $user = GraphqlAuthentication::$tokenService->getUserFromToken();

            if (isset($arguments['volume']) || isset($arguments['volumeId'])) {
                $authorOnlyVolumes = $restrictionService->getAuthorOnlyVolumes($user);

                /** @var Volumes */
                $volumesService = Craft::$app->getVolumes();

                foreach ($authorOnlyVolumes as $volume) {
                    if (isset($arguments['volume']) && trim($arguments['volume'][0]) !== $volume) {
                        continue;
                    }

                    if (isset($arguments['volumeId']) && trim((string) $arguments['volumeId'][0]) !== $volumesService->getVolumeByHandle($volume)->id) {
                        continue;
                    }

                    $arguments['uploader'] = $user->id;
                }
            } else {
                $arguments['uploader'] = $user->id;
            }
        }

        foreach ($arguments as $key => $value) {
            $query->$key($value);
        }

        $pairs = GqlHelper::extractAllowedEntitiesFromSchema('read');

        if (!GqlHelper::canQueryAssets()) {
            return [];
        }

        $query->andWhere(['in', 'assets.volumeId', array_values(Db::idsByUids(Table::VOLUMES, $pairs['volumes']))]);

        return $query;
    }
}
