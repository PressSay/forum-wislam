from deep_translator import GoogleTranslator
import os
import json
import re

# Define the source language and target languages
source_lang = 'en'
target_langs = ['es', 'zh-CN', 'ar', 'fr', 'ru', 'pt', 'hi', 'vi', 'ja', 'ko']

# Define the input files
input_files = ['auth.json', 'pagination.json', 'passwords.json', 'validation.json']

# Output directory for translated files
output_base_dir = 'resources/lang'

# Function to translate a dictionary recursively while preserving placeholders
def translate_dict(data, target_lang):
    translated = {}
    for key, value in data.items():
        if isinstance(value, dict):
            translated[key] = translate_dict(value, target_lang)
        elif isinstance(value, str) and value.strip():
            # Find all placeholders like :attribute, :seconds, etc.
            placeholders = re.findall(r':\w+', value)
            if placeholders:
                # Create a mapping of placeholders to temporary tokens
                placeholder_map = {ph: f'__PLACEHOLDER_{i}__' for i, ph in enumerate(placeholders)}
                # Replace placeholders with tokens
                temp_value = value
                for ph, token in placeholder_map.items():
                    temp_value = temp_value.replace(ph, token)
                # Translate the modified string
                translated_value = GoogleTranslator(source=source_lang, target=target_lang).translate(temp_value)
                # Restore original placeholders
                for ph, token in placeholder_map.items():
                    translated_value = translated_value.replace(token, ph)
                translated[key] = translated_value
            else:
                # No placeholders, translate directly
                translated[key] = GoogleTranslator(source=source_lang, target=target_lang).translate(value)
        else:
            translated[key] = value
    return translated

# Function to convert Python dict to PHP array syntax
def dict_to_php_array(data, indent=0):
    lines = []
    indent_str = '    ' * indent
    if isinstance(data, dict):
        lines.append('[')
        for key, value in data.items():
            if isinstance(value, (dict, list)):
                lines.append(f"{indent_str}    '{key}' => {dict_to_php_array(value, indent + 1)},")
            elif isinstance(value, str):
                lines.append(f"{indent_str}    '{key}' => '{value.replace('\'', '\\\'')}',")
            else:
                lines.append(f"{indent_str}    '{key}' => {json.dumps(value)},")
        lines.append(f"{indent_str}]")
    elif isinstance(data, list):
        lines.append('[')
        for item in data:
            lines.append(f"{indent_str}    {dict_to_php_array(item, indent + 1)},")
        lines.append(f"{indent_str}]")
    else:
        lines.append(json.dumps(data))
    return '\n'.join(lines)

# Function to process each file
def process_file(file_path, target_lang, lang_code):
    # Read JSON file
    with open(file_path, 'r', encoding='utf-8') as f:
        data = json.load(f)

    # Translate the dictionary
    translated_dict = translate_dict(data, target_lang)

    # Create output directory
    output_dir = os.path.join(output_base_dir, lang_code)
    os.makedirs(output_dir, exist_ok=True)

    # Convert to PHP array syntax
    php_output = dict_to_php_array(translated_dict)

    # Write to output file (as .php)
    output_file = os.path.join(output_dir, file_path.replace('.json', '.php'))
    with open(output_file, 'w', encoding='utf-8') as f:
        f.write('<?php\n\nreturn ' + php_output + ';\n')

# Main processing loop
for file_path in input_files:
    for target_lang in target_langs:
        lang_code = {
            'es': 'es', 'zh-CN': 'zh-Hans', 'ar': 'ar', 'fr': 'fr',
            'ru': 'ru', 'pt': 'pt', 'hi': 'hi', 'vi': 'vi', 'ja': 'ja', 'ko': 'ko'
        }[target_lang]
        print(f"Translating {file_path} to {lang_code}...")
        process_file(file_path, target_lang, lang_code)

print("Translation completed!")
