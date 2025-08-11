import React, { useState } from 'react';
import MultipleSelector, { Option } from '@/components/ui/multiple-selector';
import { cn } from '@/lib/utils';
import axios from '@/lib/axios';

interface Category {
    id: number;
    name: string;
    color?: string;
    display_color: string;
}

interface CategoryMultiSelectorProps {
    categories: Category[];
    selectedCategoryIds: number[];
    onCategoryChange: (categoryIds: number[]) => void;
    onCategoryCreated?: (category: Category) => void;
    placeholder?: string;
    disabled?: boolean;
    className?: string;
    error?: string;
    allowCreate?: boolean;
}

export default function CategoryMultiSelector({
    categories,
    selectedCategoryIds,
    onCategoryChange,
    onCategoryCreated,
    placeholder = "Select categories...",
    disabled = false,
    className,
    error,
    allowCreate = true,
}: CategoryMultiSelectorProps) {
    const [isCreating, setIsCreating] = useState(false);
    const [availableCategories, setAvailableCategories] = useState(categories);

    // Update available categories when categories prop changes, but preserve locally created categories
    React.useEffect(() => {
        setAvailableCategories(prev => {
            // Get IDs of categories from props
            const propCategoryIds = categories.map(cat => cat.id);

            // Keep locally created categories that aren't in the props yet
            const locallyCreatedCategories = prev.filter(cat => !propCategoryIds.includes(cat.id));

            // Merge prop categories with locally created ones
            return [...categories, ...locallyCreatedCategories];
        });
    }, [categories]);

    // Convert categories to options format
    const categoryOptions: Option[] = availableCategories.map((category) => ({
        value: category.id.toString(),
        label: category.name,
        // Store additional data for rendering
        color: category.display_color,
    }));

    // Convert selected category IDs to selected options
    const selectedOptions: Option[] = categoryOptions.filter((option) =>
        selectedCategoryIds.includes(parseInt(option.value))
    );

    const handleChange = async (options: Option[]) => {
        const processedOptions: Option[] = [];
        let hasFailedCreation = false;

        for (const option of options) {
            // Check if this is a newly created option (value equals label and it's not in existing categories)
            const isNewCategory = option.value === option.label &&
                !availableCategories.some(cat => cat.name.toLowerCase() === option.label.toLowerCase());

            if (isNewCategory) {
                // Create the category
                const newOption = await createCategory(option.label);
                if (newOption) {
                    processedOptions.push(newOption);
                } else {
                    // Category creation failed - don't update selection to avoid losing existing selections
                    hasFailedCreation = true;
                }
            } else {
                // Check if this is an existing category selected by name (value equals label)
                if (option.value === option.label) {
                    // Find the existing category by name and use its proper ID
                    const existingCategory = availableCategories.find(cat =>
                        cat.name.toLowerCase() === option.label.toLowerCase()
                    );
                    if (existingCategory) {
                        processedOptions.push({
                            value: existingCategory.id.toString(),
                            label: existingCategory.name,
                            color: existingCategory.display_color,
                        });
                    }
                } else {
                    // This is already a properly formatted option with ID
                    processedOptions.push(option);
                }
            }
        }

        // Only update the selection if no category creation failed
        // This prevents losing existing selections when creation fails
        if (!hasFailedCreation) {
            const categoryIds = processedOptions.map((option) => parseInt(option.value));
            onCategoryChange(categoryIds);
        }
    };

    // Function to create a new category
    const createCategory = async (name: string): Promise<Option | null> => {
        if (isCreating) return null;

        setIsCreating(true);

        try {
            const response = await axios.post(route('api.categories.store'), {
                name: name.trim(),
            });

            const data = response.data;
            const newCategory: Category = data.category;

            // Add the new category to available categories
            setAvailableCategories(prev => [...prev, newCategory]);

            // Notify parent component if callback provided
            onCategoryCreated?.(newCategory);

            // Return the new option
            return {
                value: newCategory.id.toString(),
                label: newCategory.name,
                color: newCategory.display_color,
            };
        } catch (error: any) {
            console.error('Error creating category:', error);

            // Handle validation errors
            if (error.response?.status === 422) {
                const validationErrors = error.response.data.errors;
                if (validationErrors?.name) {
                    console.error('Category validation error:', validationErrors.name[0]);
                    // You might want to show a toast notification here
                }
            } else {
                console.error('Failed to create category:', error.response?.data?.message || error.message);
            }

            return null;
        } finally {
            setIsCreating(false);
        }
    };



    return (
        <div className="space-y-2">
            <MultipleSelector
                value={selectedOptions}
                onChange={handleChange}
                options={categoryOptions}
                placeholder={placeholder}
                disabled={disabled || isCreating}
                className={cn(
                    error && 'border-destructive focus-within:ring-destructive',
                    className
                )}
                creatable={allowCreate}
                emptyIndicator={
                    <p className="text-center text-sm text-muted-foreground py-6">
                        {allowCreate ? "No categories found. Type to create a new one." : "No categories found."}
                    </p>
                }
                loadingIndicator={
                    isCreating ? (
                        <p className="text-center text-sm text-muted-foreground py-6">
                            Creating category...
                        </p>
                    ) : undefined
                }
                commandProps={{
                    className: "h-auto"
                }}
            />
            {error && (
                <p className="text-sm text-destructive">{error}</p>
            )}
        </div>
    );
}
