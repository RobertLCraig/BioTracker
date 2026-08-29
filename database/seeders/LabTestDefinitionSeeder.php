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
 * Aliases are the naming variants a lab or a person is likely to send instead of the
 * canonical name; resolveForImport() matches them on their slug, so case, spacing and
 * punctuation do not need listing as separate entries.
 *
 * Row shape: [name, unit, category, aliases?, pkb_type_id?]
 */
class LabTestDefinitionSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            // Haematology — Full Blood Count
            ['Hb', 'g/L', 'Haematology', ['Haemoglobin', 'Haemoglobin estimation', 'Hemoglobin', 'Haemoglobin concentration']],
            ['HCT', '', 'Haematology', ['Haematocrit', 'Hematocrit']],
            ['MCH', 'pg', 'Haematology', ['Mean corpuscular haemoglobin', 'Mean cell haemoglobin']],
            ['MCHC', 'g/L', 'Haematology', ['Mean corpuscular haemoglobin concentration', 'Mean cell haemoglobin concentration']],
            ['MCV', 'fL', 'Haematology', ['Mean corpuscular volume', 'Mean cell volume']],
            ['PLT', '10*9/L', 'Haematology', ['Platelets', 'Platelet count']],
            ['RBC', '10*12/L', 'Haematology', ['Red blood cell count', 'Red cell count']],
            ['WBC', '10*9/L', 'Haematology', ['White blood cell count', 'White cell count', 'Total white cell count']],
            ['Neutrophils', '10*9/L', 'Haematology', ['Neutrophil count', 'Neutrophil count (absolute)']],
            ['Lymphocytes', '10*9/L', 'Haematology', ['Lymphocyte count', 'Lymphocyte count (absolute)']],
            ['Monocytes', '10*9/L', 'Haematology', ['Monocyte count', 'Monocyte count (absolute)']],
            ['Eosinophils', '10*9/L', 'Haematology', ['Eosinophil count', 'Eosinophil count (absolute)']],
            ['Basophils', '10*9/L', 'Haematology', ['Basophil count', 'Basophil count (absolute)']],
            ['Nucleated red cells automated', '10*9/L', 'Haematology', ['Nucleated red blood cells', 'NRBC']],

            // Coagulation
            ['APTT', 'secs', 'Coagulation', ['Activated partial thromboplastin time']],
            ['APTT ratio', '', 'Coagulation', ['Activated partial thromboplastin time ratio']],
            ['Prothrombin Time', 'secs', 'Coagulation', ['PT']],
            ['INR', '', 'Coagulation', ['International normalised ratio', 'International normalized ratio']],
            ['Fibrinogen', 'g/L', 'Coagulation', ['Plasma fibrinogen', 'Fibrinogen level']],

            // Urea & Electrolytes / renal
            ['Serum sodium', 'mmol/L', 'Biochemistry', ['Sodium', 'Na', 'Plasma sodium']],
            ['Serum potassium', 'mmol/L', 'Biochemistry', ['Potassium', 'Plasma potassium']],
            ['Serum urea', 'mmol/L', 'Biochemistry', ['Urea', 'Blood urea', 'Urea level']],
            ['Serum creatinine', 'umol/L', 'Biochemistry', ['Creatinine', 'Creat', 'Creatinine level']],
            ['eGFR (MDRD) per 1.73 sq m', 'mL/min', 'Biochemistry', ['eGFR', 'Estimated GFR', 'GFR calculated abbreviated MDRD']],
            ['EGFR comment 1', '', 'Biochemistry'],

            // Liver Function Tests
            ['Serum albumin', 'g/L', 'Liver', ['Albumin', 'Albumin level']],
            ['Serum total protein', 'g/L', 'Liver', ['Total protein']],
            ['Serum globulin', 'g/L', 'Liver', ['Globulin']],
            ['Serum alkaline phosphatase', 'iu/L', 'Liver', ['Alkaline phosphatase', 'ALP']],
            ['Serum total bilirubin', 'umol/L', 'Liver', ['Bilirubin', 'Total bilirubin', 'Serum bilirubin']],
            ['Serum ALT', 'iu/L', 'Liver', ['ALT', 'Alanine aminotransferase', 'Serum alanine aminotransferase level']],
            ['Serum AST', 'iu/L', 'Liver', ['AST', 'Aspartate aminotransferase']],
            ['Serum GGT', 'iu/L', 'Liver', ['GGT', 'Gamma GT', 'Gamma-glutamyl transferase']],

            // Lipid profile — PKB ids known
            ['Serum cholesterol', 'mmol/L', 'Lipids', ['Cholesterol', 'Total cholesterol', 'Serum total cholesterol'], '943400139'],
            ['Serum HDL cholesterol', 'mmol/L', 'Lipids', ['HDL', 'HDL cholesterol'], '943400140'],
            ['Serum chol:HDL ratio', '', 'Lipids', ['Chol:HDL ratio', 'Cholesterol/HDL ratio', 'Total cholesterol:HDL ratio'], '943400141'],
            ['Serum triglyceride', 'mmol/L', 'Lipids', ['Triglyceride', 'Triglycerides', 'Serum triglycerides'], '943400142'],
            ['Serum non-HDL cholesterol', 'mmol/L', 'Lipids', ['Non-HDL cholesterol'], '943400143'],
            ['Serum LDL cholesterol', 'mmol/L', 'Lipids', ['LDL', 'LDL cholesterol'], '943400144'],

            // Iron studies
            ['Serum iron', 'umol/L', 'Iron', ['Iron', 'Iron level']],
            ['Serum transferrin', 'g/L', 'Iron', ['Transferrin']],
            ['Serum transferrin % saturation', '%', 'Iron', ['Transferrin saturation', 'Transferrin saturation index']],
            ['Serum Ferritin', 'ug/L', 'Iron', ['Ferritin', 'Ferritin level']],

            // Inflammation
            ['Serum C-reactive protein', 'mg/L', 'Inflammation', ['CRP', 'C-reactive protein', 'C reactive protein level']],

            // Diabetes
            ['Haemoglobin A1c (IFCC aligned)', 'mmol/mol', 'Diabetes', ['HbA1c', 'Hb A1c', 'Haemoglobin A1c', 'HbA1c level (IFCC standardised)']],
            ['Comment for HbA1c', '', 'Diabetes'],

            // Endocrinology
            ['Serum free testosterone', 'pmol/L', 'Endocrinology', ['Free testosterone']],
            ['Serum total testosterone', 'nmol/L', 'Endocrinology', ['Testosterone', 'Total testosterone']],
            ['Serum SHBG', 'nmol/L', 'Endocrinology', ['SHBG', 'Sex hormone binding globulin']],

            // Faecal
            ['FAECAL CALPROTECTIN', 'ug/g', 'Faecal', ['Calprotectin']],
            ['Faecal occult blood (FIT)', 'ug/g', 'Faecal', ['FIT', 'Faecal immunochemical test', 'Faecal occult blood']],
            ['Faecal occult blood comment', '', 'Faecal'],
        ];

        foreach ($rows as $row) {
            [$name, $unit, $category] = $row;
            $aliases = $row[3] ?? [];
            $pkbTypeId = $row[4] ?? null;

            LabTestDefinition::updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'default_unit' => $unit !== '' ? $unit : null,
                    'category' => $category,
                    'aliases' => $aliases ?: null,
                    'pkb_type_id' => $pkbTypeId,
                    'code_system' => $pkbTypeId ? 'loincMapping' : null,
                    'is_curated' => true,
                ],
            );
        }
    }
}
