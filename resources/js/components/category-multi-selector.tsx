import React, { useState } from 'react';
import axios from 'axios';
import MultipleSelector, { Option } from '@/components/ui/multiple-selector';
import { cn } from '@/lib/utils';

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

    // Function to create a new category
    const createCategory = async (name: string): Promise<Option | null> => {
        if (isCreating) return null;

        setIsCreating(true);

        try {
            const response = await axios.post(route('api.categories.store'), {
                name: name.trim(),
            });

            if (response.data.success && response.data.category) {
                const newCategory: Category = response.data.category;

                // Add the new category to available categories
                setAvailableCategories(prev => [...prev, newCategory]);

                // Notify parent component if callback provided
                onCategoryCreated?.(newCategory);

                // Return the new option
                const newOption: Option = {
                    value: newCategory.id.toString(),
                    label: newCategory.name,
                    color: newCategory.display_color,
                };

                setIsCreating(false);
                return newOption;
            } else {
                console.error('Invalid API response format');
                setIsCreating(false);
                return null;
            }
        } catch (error: any) {
            console.error('Error creating category:', error);

            // Handle validation errors
            if (error.response?.data?.errors?.name) {
                const nameError = error.response.data.errors.name;
                console.error('Category validation error:', Array.isArray(nameError) ? nameError[0] : nameError);
            } else {
                console.error('Failed to create category');
            }

            setIsCreating(false);
            return null;
        }
    };

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
                // This is an existing category
                processedOptions.push(option);
            }
        }

        // Only update the selection if no category creation failed
        if (!hasFailedCreation) {
            const categoryIds = processedOptions.map(option => parseInt(option.value));
            onCategoryChange(categoryIds);
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
