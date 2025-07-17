-- Add from_receipt_note column to purchase_entries table
-- Run this SQL command in your MySQL database

ALTER TABLE purchase_entries 
ADD COLUMN from_receipt_note BOOLEAN DEFAULT FALSE 
AFTER note;

-- Verify the column was added
DESCRIBE purchase_entries;