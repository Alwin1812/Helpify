ALTER TABLE services ADD COLUMN IF NOT EXISTS icon VARCHAR(255) DEFAULT 'help_outline';
UPDATE services SET icon = 'cleaning_services' WHERE name = 'Cleaning';
UPDATE services SET icon = 'rice_bowl' WHERE name = 'Cooking';
UPDATE services SET icon = 'child_care' WHERE name = 'Babysitting';
UPDATE services SET icon = 'elderly' WHERE name = 'Elderly Care';
UPDATE services SET icon = 'plumbing' WHERE name = 'Plumbing';
