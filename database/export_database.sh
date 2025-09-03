#!/bin/bash

# Database Export Script for rc_convergio_s
# This script exports the MySQL database to avoid migration issues

# Default values
DATABASE_NAME="rc_convergio_s"
USERNAME="root"
PASSWORD=""
HOST="localhost"
PORT="3306"
OUTPUT_FILE="db_dump.sql"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

print_info() {
    echo -e "${CYAN}📋 $1${NC}"
}

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --database|-d)
            DATABASE_NAME="$2"
            shift 2
            ;;
        --username|-u)
            USERNAME="$2"
            shift 2
            ;;
        --password|-p)
            PASSWORD="$2"
            shift 2
            ;;
        --host|-h)
            HOST="$2"
            shift 2
            ;;
        --port)
            PORT="$2"
            shift 2
            ;;
        --output|-o)
            OUTPUT_FILE="$2"
            shift 2
            ;;
        --help)
            echo "Usage: $0 [OPTIONS]"
            echo "Options:"
            echo "  -d, --database NAME    Database name (default: rc_convergio_s)"
            echo "  -u, --username NAME    MySQL username (default: root)"
            echo "  -p, --password PASS    MySQL password (default: empty)"
            echo "  -h, --host HOST        MySQL host (default: localhost)"
            echo "  --port PORT            MySQL port (default: 3306)"
            echo "  -o, --output FILE      Output file name (default: db_dump.sql)"
            echo "  --help                 Show this help message"
            exit 0
            ;;
        *)
            print_error "Unknown option: $1"
            exit 1
            ;;
    esac
done

echo -e "${GREEN}🚀 Starting database export process...${NC}"

# Check if mysqldump is available
if ! command -v mysqldump &> /dev/null; then
    print_error "mysqldump not found. Please ensure MySQL is installed and in your PATH."
    print_warning "If using XAMPP on Windows, try: C:\\xampp\\mysql\\bin\\mysqldump.exe"
    exit 1
fi

print_status "mysqldump found: $(mysqldump --version | head -n1)"

# Build the mysqldump command
MYSQLDUMP_CMD="mysqldump"
MYSQLDUMP_ARGS=(
    "--host=$HOST"
    "--port=$PORT"
    "--user=$USERNAME"
    "--single-transaction"
    "--routines"
    "--triggers"
    "--events"
    "--add-drop-database"
    "--create-options"
    "--comments"
    "--dump-date"
    "--complete-insert"
    "--extended-insert"
    "--set-charset"
    "--default-character-set=utf8mb4"
    "$DATABASE_NAME"
)

# Add password if provided
if [ -n "$PASSWORD" ]; then
    MYSQLDUMP_ARGS+=("--password=$PASSWORD")
fi

print_info "Executing: $MYSQLDUMP_CMD ${MYSQLDUMP_ARGS[*]}"
print_info "Output will be saved to: database/$OUTPUT_FILE"

# Execute the command
if "$MYSQLDUMP_CMD" "${MYSQLDUMP_ARGS[@]}" > "database/$OUTPUT_FILE" 2>/dev/null; then
    # Get file size
    if command -v stat &> /dev/null; then
        FILE_SIZE=$(stat -c%s "database/$OUTPUT_FILE" 2>/dev/null || stat -f%z "database/$OUTPUT_FILE" 2>/dev/null)
    else
        FILE_SIZE=$(wc -c < "database/$OUTPUT_FILE")
    fi
    
    FILE_SIZE_MB=$(echo "scale=2; $FILE_SIZE / 1024 / 1024" | bc 2>/dev/null || echo "unknown")
    
    print_status "Database export completed successfully!"
    print_info "File size: ${FILE_SIZE_MB}MB"
    print_info "Location: database/$OUTPUT_FILE"
    
    # Add to git
    print_warning "Adding to git..."
    if git add "database/$OUTPUT_FILE"; then
        print_status "File added to git staging area"
        print_warning "Run 'git commit -m \"Update database dump\"' to commit the changes"
    else
        print_warning "Git add failed. You may need to commit manually."
    fi
    
else
    print_error "Database export failed"
    exit 1
fi

echo ""
echo -e "${GREEN}🎉 Process completed! Your team can now:${NC}"
echo -e "   1. Pull the latest code"
echo -e "   2. Go to phpMyAdmin"
echo -e "   3. Import database/$OUTPUT_FILE into $DATABASE_NAME"
echo -e "   4. Skip running php artisan migrate"
