-- Fragment begins: 5 --
INSERT INTO changelog
                                (change_number, delta_set, start_dt, applied_by, description) VALUES (5, 'Main', NOW(), 'dbdeploy', 'UPDATE_05-11-2017.sql');
ALTER TABLE `melis_cms_news` ADD `cnews_unpublish_date` DATETIME NULL AFTER `cnews_publish_date`;
ALTER TABLE `melis_cms_news` ADD `cnews_site_id` INT NULL ;
UPDATE changelog
	                         SET complete_dt = NOW()
	                         WHERE change_number = 5
	                         AND delta_set = 'Main';
-- Fragment ends: 5 --
