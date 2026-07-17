<?php

namespace Database\Seeders;

use App\Models\LabTestDefinition;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Starter catalog of common UK pathology analytes, grouped by category.
 * PKB testResultTypeId is filled where known (the lipid panel, captured
 * 2026-07-17); the rest backfill their pkb_type_id on first import.
 *
 * Row shape: [name, unit, category, pkb_type_id?]
 */
class LabTestDefinitionSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            // Haematology — Full Blood Count
            ['Hb', 'g/L', 'Haematology'],
            ['HCT', '', 'Haematology'],
            ['MCH', 'pg', 'Haematology'],
            ['MCHC', 'g/L', 'Haematology'],
            ['MCV', 'fL', 'Haematology'],
            ['PLT', '10*9/L', 'Haematology'],
            ['RBC', '10*12/L', 'Haematology'],
            ['WBC', '10*9/L', 'Haematology'],
            ['Neutrophils', '10*9/L', 'Haematology'],
            ['Lymphocytes', '10*9/L', 'Haematology'],
            ['Monocytes', '10*9/L', 'Haematology'],
            ['Eosinophils', '10*9/L', 'Haematology'],
            ['Basophils', '10*9/L', 'Haematology'],
            ['Nucleated red cells automated', '10*9/L', 'Haematology'],

            // Coagulation
            ['APTT', 'secs', 'Coagulation'],
            ['APTT ratio', '', 'Coagulation'],
            ['Prothrombin Time', 'secs', 'Coagulation'],
            ['INR', '', 'Coagulation'],
            ['Fibrinogen', 'g/L', 'Coagulation'],

            // Urea & Electrolytes / renal
            ['Serum sodium', 'mmol/L', 'Biochemistry'],
            ['Serum potassium', 'mmol/L', 'Biochemistry'],
            ['Serum urea', 'mmol/L', 'Biochemistry'],
            ['Serum creatinine', 'umol/L', 'Biochemistry'],
            ['eGFR (MDRD) per 1.73 sq m', 'mL/min', 'Biochemistry'],
            ['EGFR comment 1', '', 'Biochemistry'],

            // Liver Function Tests
            ['Serum albumin', 'g/L', 'Liver'],
            ['Serum total protein', 'g/L', 'Liver'],
            ['Serum globulin', 'g/L', 'Liver'],
            ['Serum alkaline phosphatase', 'iu/L', 'Liver'],
            ['Serum total bilirubin', 'umol/L', 'Liver'],
            ['Serum ALT', 'iu/L', 'Liver'],
            ['Serum AST', 'iu/L', 'Liver'],
            ['Serum GGT', 'iu/L', 'Liver'],

            // Lipid profile — PKB ids known
            ['Serum cholesterol', 'mmol/L', 'Lipids', '943400139'],
            ['Serum HDL cholesterol', 'mmol/L', 'Lipids', '943400140'],
            ['Serum chol:HDL ratio', '', 'Lipids', '943400141'],
            ['Serum triglyceride', 'mmol/L', 'Lipids', '943400142'],
            ['Serum non-HDL cholesterol', 'mmol/L', 'Lipids', '943400143'],
            ['Serum LDL cholesterol', 'mmol/L', 'Lipids', '943400144'],

            // Iron studies
            ['Serum iron', 'umol/L', 'Iron'],
            ['Serum transferrin', 'g/L', 'Iron'],
            ['Serum transferrin % saturation', '%', 'Iron'],
            ['Serum Ferritin', 'ug/L', 'Iron'],

            // Inflammation
            ['Serum C-reactive protein', 'mg/L', 'Inflammation'],

            // Diabetes
            ['Haemoglobin A1c (IFCC aligned)', 'mmol/mol', 'Diabetes'],
            ['Comment for HbA1c', '', 'Diabetes'],

            // Endocrinology
            ['Serum free testosterone', 'pmol/L', 'Endocrinology'],
            ['Serum total testosterone', 'nmol/L', 'Endocrinology'],
            ['Serum SHBG', 'nmol/L', 'Endocrinology'],

            // Faecal
            ['FAECAL CALPROTECTIN', 'ug/g', 'Faecal'],
            ['Faecal occult blood (FIT)', 'ug/g', 'Faecal'],
            ['Faecal occult blood comment', '', 'Faecal'],
        ];

        foreach ($rows as $row) {
            [$name, $unit, $category] = $row;
            $pkbTypeId = $row[3] ?? null;

            LabTestDefinition::updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'default_unit' => $unit !== '' ? $unit : null,
                    'category' => $category,
                    'pkb_type_id' => $pkbTypeId,
                    'code_system' => $pkbTypeId ? 'loincMapping' : null,
                    'is_curated' => true,
                ],
            );
        }
    }
}
