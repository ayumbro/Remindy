import React from 'react';
import MultipleSelector, { Option } from '@/components/ui/multiple-selector';
import { Badge } from '@/components/ui/badge';
import { Command, CommandGroup, CommandItem, CommandList } from '@/components/ui/command';
import { cn } from '@/lib/utils';
import { X } from 'lucide-react';

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
    placeholder?: string;
    disabled?: boolean;
    className?: string;
    error?: string;
}

export default function CategoryMultiSelector({
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
        // Store additional data for rendering
        color: category.display_color,
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
