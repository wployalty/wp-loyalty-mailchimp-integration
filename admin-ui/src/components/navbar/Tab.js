import React from "react";
import {Link, useLocation} from "react-router-dom";

const Tab = ({tab}) => {
    const currentPath = useLocation().pathname.split("/")[1];
    return <Link to={tab.path}
                 className={`relative  shadow-none focus:outline-none  transition duration-200 ease-in`}
    >
        <p className={`py-3 px-6 2xl:text-md text-sm font-medium ${tab.check.includes(currentPath) ? "text-blue_primary" : "text-light"} `}>
            {tab.label}
        </p>
        {tab.check.includes(currentPath) &&
            <span
                className={`h-1 absolute rounded-t-lg bg-blue_primary transition duration-200 ease-in bottom-0  left-2.5 w-[75%]`}/>}
    </Link>
};

export default Tab;
