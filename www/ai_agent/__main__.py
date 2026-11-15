"""Entry point для `python -m ai_agent`."""
import sys

from .orchestrator import main

if __name__ == "__main__":
    sys.exit(main())