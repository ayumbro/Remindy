import React, { useState } from 'react';
import MultipleSelector, { Option } from '@/components/ui/multiple-selector';
import { Badge } from '@/components/ui/badge';
import { Command, CommandGroup, CommandItem, CommandList } from '@/components/ui/command';
import { cn } from '@/lib/utils';
import { X } from 'lucide-react';
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
    // Update available categories when categories prop changes
    React.useEffect(() => {
        setAvailableCategories(categories);
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

        for (const option of options) {
            // Check if this is a newly created option (value equals label and it's not in existing categories)
            const isNewCategory = option.value === option.label &&
                !availableCategories.some(cat => cat.id.toString() === option.value);

            if (isNewCategory) {
                // Create the category
                const newOption = await createCategory(option.label);
                if (newOption) {
                    processedOptions.push(newOption);
                }
            } else {
                processedOptions.push(option);
            }
        }

        const categoryIds = processedOptions.map((option) => parseInt(option.value));
        onCategoryChange(categoryIds);
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

    // Find category by ID for color lookup
    const getCategoryById = (id: number) => availableCategories.find(cat => cat.id === id);

    return (
        <div className="space-y-2">
            <MultipleSelector
                value={selectedOptions}
                onChange={handleChange}
                defaultOptions={categoryOptions}
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

// Custom component that renders category options with color indicators
interface CategoryOptionProps {
    category: Category;
    isSelected: boolean;
    onSelect: () => void;
}

function CategoryOption({ category, isSelected, onSelect }: CategoryOptionProps) {
    return (
        <div
            className={cn(
                "flex items-center gap-2 px-2 py-1.5 text-sm cursor-pointer rounded-sm hover:bg-accent hover:text-accent-foreground",
                isSelected && "bg-accent text-accent-foreground"
            )}
            onClick={onSelect}
        >
            <div
                className="h-3 w-3 rounded-full flex-shrink-0"
                style={{ backgroundColor: category.display_color }}
            />
            <span className="flex-1">{category.name}</span>
        </div>
    );
}

// Enhanced version with better visual representation
export function CategoryMultiSelectorWithColors({
    categories,
    selectedCategoryIds,
    onCategoryChange,
    placeholder = "Select categories...",
    disabled = false,
    className,
    error,
}: CategoryMultiSelectorProps) {
    // Convert categories to options format
    const categoryOptions: Option[] = categories.map((category) => ({
        value: category.id.toString(),
        label: category.name,
    }));

    // Convert selected category IDs to selected options
    const selectedOptions: Option[] = categoryOptions.filter((option) =>
        selectedCategoryIds.includes(parseInt(option.value))
    );

    const handleChange = (options: Option[]) => {
        const categoryIds = options.map((option) => parseInt(option.value));
        onCategoryChange(categoryIds);
    };

    // Find category by ID for color lookup
    const getCategoryById = (id: number) => categories.find(cat => cat.id === id);

    return (
        <div className="space-y-2">
            <MultipleSelector
                value={selectedOptions}
                onChange={handleChange}
                defaultOptions={categoryOptions}
                placeholder={placeholder}
                disabled={disabled}
                className={cn(
                    error && 'border-destructive focus-within:ring-destructive',
                    className
                )}
                emptyIndicator={
                    <p className="text-center text-sm text-muted-foreground py-6">
                        No categories found.
                    </p>
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
