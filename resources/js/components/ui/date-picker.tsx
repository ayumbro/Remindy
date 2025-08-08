"use client"

import * as React from "react"
import { format } from "date-fns"
import { CalendarIcon, X } from "lucide-react"

import { cn } from "@/lib/utils"
import { Button } from "@/components/ui/button"
import { Calendar } from "@/components/ui/calendar"
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover"

interface DatePickerProps {
  value?: string
  onChange?: (value: string) => void
  placeholder?: string
  disabled?: boolean
  className?: string
  id?: string
  name?: string
  required?: boolean
  min?: string
  max?: string
  showClearButton?: boolean
  clearButtonLabel?: string
}

export function DatePicker({
  value,
  onChange,
  placeholder = "Pick a date",
  disabled = false,
  className,
  id,
  name,
  required = false,
  min,
  max,
  showClearButton = true,
  clearButtonLabel = "Clear date",
}: DatePickerProps) {
  const [open, setOpen] = React.useState(false)

  // Convert string date to Date object
  const selectedDate = value ? new Date(value) : undefined

  // Convert min/max strings to Date objects
  const minDate = min ? new Date(min) : undefined
  const maxDate = max ? new Date(max) : undefined

  // Calculate year range for dropdown (default: 100 years back to 10 years forward)
  const currentYear = new Date().getFullYear()
  const startYear = minDate ? minDate.getFullYear() : currentYear - 100
  const endYear = maxDate ? maxDate.getFullYear() : currentYear + 10

  const handleSelect = (date: Date | undefined) => {
    if (date && onChange) {
      // Format date as YYYY-MM-DD for form compatibility
      const formattedDate = format(date, "yyyy-MM-dd")
      onChange(formattedDate)
    }
    setOpen(false)
  }

  const handleClear = (e: React.MouseEvent) => {
    e.stopPropagation() // Prevent opening the popover
    if (onChange) {
      onChange("")
    }
  }

  // Get user's locale for date formatting
  const userLocale = navigator.language || 'en-US'

  // Format date for display using user's locale
  const formatDateForDisplay = (date: Date) => {
    return new Intl.DateTimeFormat(userLocale, {
      year: 'numeric',
      month: 'long',
      day: 'numeric'
    }).format(date)
  }

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <div className="relative">
          <Button
            id={id}
            variant="outline"
            className={cn(
              "w-full justify-start text-left font-normal",
              !selectedDate && "text-muted-foreground",
              showClearButton && selectedDate && "pr-10", // Add padding for clear button
              className
            )}
            disabled={disabled}
            type="button"
          >
            <CalendarIcon className="mr-2 h-4 w-4" />
            {selectedDate ? (
              formatDateForDisplay(selectedDate)
            ) : (
              <span>{placeholder}</span>
            )}
          </Button>
          {showClearButton && selectedDate && !disabled && (
            <Button
              type="button"
              variant="ghost"
              size="sm"
              className="absolute right-1 top-1/2 h-6 w-6 -translate-y-1/2 p-0 hover:bg-destructive hover:text-destructive-foreground"
              onClick={handleClear}
              aria-label={clearButtonLabel}
              title={clearButtonLabel}
            >
              <X className="h-3 w-3" />
            </Button>
          )}
        </div>
      </PopoverTrigger>
      <PopoverContent className="w-auto p-0" align="start">
        <Calendar
          mode="single"
          selected={selectedDate}
          onSelect={handleSelect}
          disabled={(date) => {
            if (disabled) return true
            if (minDate && date < minDate) return true
            if (maxDate && date > maxDate) return true
            return false
          }}
          defaultMonth={selectedDate || new Date()}
          fromYear={startYear}
          toYear={endYear}
          captionLayout="dropdown"
          initialFocus
        />
      </PopoverContent>
      {/* Hidden input for form compatibility */}
      <input
        type="hidden"
        name={name}
        value={value || ""}
        required={required}
      />
    </Popover>
  )
}

// Alternative component for cases where we need more control
interface DatePickerInputProps extends DatePickerProps {
  error?: boolean
}

export function DatePickerInput({
  value,
  onChange,
  placeholder = "Select date",
  disabled = false,
  className,
  id,
  name,
  required = false,
  min,
  max,
  error = false,
  showClearButton = true,
  clearButtonLabel = "Clear date",
}: DatePickerInputProps) {
  return (
    <DatePicker
      value={value}
      onChange={onChange}
      placeholder={placeholder}
      disabled={disabled}
      className={cn(
        error && "border-destructive focus:border-destructive",
        className
      )}
      id={id}
      name={name}
      required={required}
      min={min}
      max={max}
      showClearButton={showClearButton}
      clearButtonLabel={clearButtonLabel}
    />
  )
}
